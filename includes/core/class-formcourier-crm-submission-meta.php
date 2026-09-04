<?php
/**
 * Submission metadata helper.
 *
 * @package FormCourierCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Submission Meta.
 */
final class FormCourier_CRM_Submission_Meta {

	/**
	 * Handles the append form data operation.
	 *
	 * @param array  $data      Data.
	 * @param mixed  $form_id   Form id.
	 * @param string $form_name Form name.
	 */
	public static function append_form_data( array $data, $form_id, string $form_name = '' ): array {
		$form_id = is_scalar( $form_id )
			? trim( sanitize_text_field( (string) $form_id ) )
			: '';

		$form_name = trim( sanitize_text_field( $form_name ) );

		if ( '' !== $form_id && '0' !== $form_id ) {
			$data['form_id'] = $form_id;
		}

		if ( '' !== $form_name ) {
			$data['form_name'] = $form_name;
		}

		/**
		 * Allows developers to customize automatically added submission metadata.
		 *
		 * Default service fields:
		 * - form_id
		 * - form_name
		 */
		return apply_filters( 'formcourier_crm_submission_meta_data', $data, $form_id, $form_name );
	}

	/**
	 * Extracts the email.
	 *
	 * @param array $data Data.
	 */
	public static function extract_email( array $data ): string {
		$preferred_keys = array(
			'email',
			'Email',
			'EMAIL',
			'e-mail',
			'E-mail',
			'E-mail Address',
			'Email Address',
			'Email адреса',
			'Email адрес',
			'Email адреси',
			'your-email',
			'user_email',
			'contact.email',
			'contact_email',
			'person.email',
			'person_email',
		);

		foreach ( $preferred_keys as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}

			$email = self::extract_email_from_value( $data[ $key ] );

			if ( '' !== $email ) {
				return $email;
			}
		}

		return self::extract_email_from_value( $data );
	}

	/**
	 * Extracts the email from value.
	 *
	 * @param mixed $value Value.
	 */
	private static function extract_email_from_value( $value ): string {
		if ( is_object( $value ) ) {
			$value = (array) $value;
		}

		if ( is_array( $value ) ) {
			$preferred_keys = array(
				'VALUE',
				'value',
				'email',
				'Email',
				'EMAIL',
				'address',
			);

			foreach ( $preferred_keys as $key ) {
				if ( ! array_key_exists( $key, $value ) ) {
					continue;
				}

				$email = self::extract_email_from_value( $value[ $key ] );

				if ( '' !== $email ) {
					return $email;
				}
			}

			foreach ( $value as $item ) {
				$email = self::extract_email_from_value( $item );

				if ( '' !== $email ) {
					return $email;
				}
			}

			return '';
		}

		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = trim( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		$email = sanitize_email( $value );

		if ( is_email( $email ) ) {
			return $email;
		}

		if ( preg_match_all( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $value, $matches ) ) {
			foreach ( $matches[0] as $match ) {
				$email = sanitize_email( $match );

				if ( is_email( $email ) ) {
					return $email;
				}
			}
		}

		return '';
	}
}
