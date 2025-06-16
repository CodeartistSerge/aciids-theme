<?php
	define('MAILCHIMP_TAG_TO_PRODUCTS', [
		'conf-2025-attendee' => [
			2870,
		],
		'conf-2025-rec' => [
			2872,
		],
	]);

	function ca_mailchimp_subscribe( $email, $list_id, $tags = [], $apiKey ) {
		if (!$apiKey) return false;
		try {
			/*
				ENDPOINT: https://${dc}.api.mailchimp.com/3.0/lists/{list_id}/members
			*/
			$memberId = md5(strtolower($email));
			$dc = substr($apiKey, strpos($apiKey, '-') + 1);
			$url = "https://$dc.api.mailchimp.com/3.0/lists/$list_id/members/$memberId";
			$data = [
				'email_address' => $email,
				'status' => 'subscribed',
				'tags' => $tags,
			];
			$ch = curl_init($url);

			curl_setopt($ch, CURLOPT_USERPWD, 'user:' . $apiKey);
			curl_setopt($ch, CURLOPT_HTTPHEADER, [
				'Content-Type: application/json'
			]);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_TIMEOUT, 10);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
			$response = curl_exec($ch);
			$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);
			if ($httpCode >= 200 && $httpCode < 300) {
				// Successfully subscribed
				return true;
			} else {
				// Handle error
				$today = date('Y-m-d H:i:s');
				@file_put_contents( __DIR__ . '/error.log', "$today | Mailchimp API error: $response\n", FILE_APPEND );
				return false;
			}
		} catch (\Exception $e) {
			$today = date('Y-m-d H:i:s');
			@file_put_contents( __DIR__ . '/error.log', "$today | Exception while subscribing to Mailchimp: " . $e->getMessage() . "\n", FILE_APPEND );
			return false;
		}
	}

	add_action('woocommerce_thankyou', function( $order_id ) {
		if ( ! $order_id ) return;
		// Allow code execution only once
		if( ! get_post_meta( $order_id, '_thankyou_action_done', true ) ) {
			$mcIni = parse_ini_file(__DIR__ . '/env.ini');
			if( !is_array( $mcIni ) || !array_key_exists('MAILCHIMP_API_KEY', $mcIni ) || !$mcIni['MAILCHIMP_API_KEY'] ) return;
			if( !is_array( $mcIni ) || !array_key_exists('MAILCHIMP_LIST_ID', $mcIni ) || !$mcIni['MAILCHIMP_LIST_ID'] ) return;
			$order = wc_get_order( $order_id );
			$order_key = $order->get_order_key();
			$order_key = $order->get_order_number();

			$isPaid = $order->is_paid();

			// Loop through order items
			foreach ( $order->get_items() as $item_id => $item ) {
				$product = $item->get_product();
				$product_id = $product->get_id();
				foreach ( MAILCHIMP_TAG_TO_PRODUCTS as $tag => $product_ids ) {
					if ( in_array( $product_id, $product_ids ) ) {
						$list_id = $mcIni['MAILCHIMP_LIST_ID'];
						$email = $order->get_billing_email();
						$apiKey = $mcIni['MAILCHIMP_API_KEY'];
						$tags = [$tag];
						ca_mailchimp_subscribe( $email, $list_id, $tags, $apiKey );
					}
				}
			}
			// Flag the action as done (to avoid repetitions on reload for example)
			$order->update_meta_data( '_thankyou_action_done', true );
			$order->save();
		}
	}, 99999, 1);
