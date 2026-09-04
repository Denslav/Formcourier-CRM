<?php
/**
 * Form data sanitizer.
 *
 * @package FormCourierCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalizes values received from supported WordPress form plugins.
 *
 * Form fields may contain textareas, checkbox arrays, composite names and
 * nested values. This helper keeps line breaks in user messages while
 * removing HTML and unsupported value types consistently across providers.
 */
final class FormCourier_CRM_Form_Data_Sanitizer {

	/**
	 * Sanitizes a form field key or label for use as an array key.
	 *
	 * @param mixed $field_name Raw field name.
	 */
	public static function sanitize_field_name( $field_name ): string {
		if ( is_array( $field_name ) || is_object( $field_name ) || is_resource( $field_name ) ) {
			return '';
		}

		return sanitize_text_field( (string) $field_name );
	}

	/**
	 * Sanitizes a form value while preserving meaningful line breaks.
	 *
	 * Arrays are recursively flattened. Scalar values are processed with
	 * sanitize_textarea_field() so textarea content is not collapsed into a
	 * single line.
	 *
	 * @param mixed  $field_value Raw field value.
	 * @param string $separator   Separator used when an array is flattened.
	 */
	public static function sanitize_value( $field_value, string $separator = ', ' ): string {
		if ( is_array( $field_value ) ) {
			$values = array();

			foreach ( $field_value as $value ) {
				$value = self::sanitize_value( $value, $separator );

				if ( '' !== $value ) {
					$values[] = $value;
				}
			}

			return implode( $separator, $values );
		}

		if ( is_object( $field_value ) || is_resource( $field_value ) ) {
			return '';
		}

		return sanitize_textarea_field( (string) $field_value );
	}

	/**
	 * Adds a sanitized non-empty field to normalized submission data.
	 *
	 * @param array<string,string> $clean_data  Destination data.
	 * @param mixed                $field_name  Raw field key or label.
	 * @param mixed                $field_value Raw field value.
	 * @param string               $separator   Array value separator.
	 */
	public static function add_field( array &$clean_data, $field_name, $field_value, string $separator = ', ' ): void {
		$field_name = self::sanitize_field_name( $field_name );

		if ( '' === $field_name ) {
			return;
		}

		$field_value = self::sanitize_value( $field_value, $separator );

		if ( '' === $field_value ) {
			return;
		}

		$clean_data[ $field_name ] = $field_value;
	}
}
