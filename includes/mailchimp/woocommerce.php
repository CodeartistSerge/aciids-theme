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
				curl -X POST \
					'https://${dc}.api.mailchimp.com/3.0/lists/{list_id}/members' \
					--user "anystring:${apikey}"' \
					-d '{"email_address":"","email_type":"","status":"subscribed","merge_fields":{},"interests":{},"language":"","vip":false,"location":{"latitude":0,"longitude":0},"marketing_permissions":[],"ip_signup":"","timestamp_signup":"","ip_opt":"","timestamp_opt":"","tags":[]}'
			*/
			$dc = substr($apiKey, strpos($apiKey, '-') + 1);
			$url = "https://$dc.api.mailchimp.com/3.0/lists/$list_id/members";
			$data = [
				'email_address' => $email,
				'status' => 'subscribed',
				'tags' => $tags,
			];
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
			curl_setopt($ch, CURLOPT_USERPWD, 'user:' . $apiKey);
			curl_setopt($ch, CURLOPT_TIMEOUT, 10);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_HTTPHEADER, [
				'Content-Type: application/json'
			]);
			$response = curl_exec($ch);
			$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);
			if ($httpCode >= 200 && $httpCode < 300) {
				// Successfully subscribed
				return true;
			} else {
				// Handle error
				@file_put_contents( __DIR__ . '/test.log', "Mailchimp API error: $response\n", FILE_APPEND );
				return false;
			}
		} catch (\Exception $e) {
			@file_put_contents( __DIR__ . '/test.log', "Exception while subscribing to Mailchimp: " . $e->getMessage() . "\n", FILE_APPEND );
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
						// TODO: Add tag to Mailchimp
						$list_id = $mcIni['MAILCHIMP_LIST_ID'];
						$email = $order->get_billing_email();
						$apiKey = $mcIni['MAILCHIMP_API_KEY'];
						$tags = [$tag];
						if ( ca_mailchimp_subscribe( $email, $list_id, $tags, $apiKey ) ) {
							@file_put_contents( __DIR__ . '/test.log', "Subscribed $email to tag '$tag' for product ID $product_id in order $order_key\n", FILE_APPEND );
						} else {
							@file_put_contents( __DIR__ . '/test.log', "Failed to subscribe $email to tag '$tag' for product ID $product_id in order $order_key\n", FILE_APPEND );
						}
					}
				}
			}
			// Flag the action as done (to avoid repetitions on reload for example)
			$order->update_meta_data( '_thankyou_action_done', true );
			$order->save();
		}
	}, 10, 1);
