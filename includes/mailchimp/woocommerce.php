<?php
	define('MAILCHIMP_TAG_TO_PRODUCTS', [
		'conf-2025-attendee' => [
			2870,
		],
		'conf-2025-rec' => [
			2872,
		],
	]);

	add_action('woocommerce_thankyou', function( $order_id ) {
		@file_put_contents( __DIR__ . '/test.log', "Order ID: $order_id\n---\n", FILE_APPEND );
		if ( ! $order_id ) return;
		// Allow code execution only once
		if( ! get_post_meta( $order_id, '_thankyou_action_done', true ) ) {
			@file_put_contents( __DIR__ . '/test.log', "Order ID: $order_id\n---\n", FILE_APPEND );
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
						@file_put_contents( __DIR__ . '/test.log', "Adding customer associated with order $order_id to Mailchimp tag '$tag' for product ID $product_id\n", FILE_APPEND );
					}
				}
			}

			// Flag the action as done (to avoid repetitions on reload for example)
			$order->update_meta_data( '_thankyou_action_done', true );
			$order->save();
		}
	}, 10, 1);
