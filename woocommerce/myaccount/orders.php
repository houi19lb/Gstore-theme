<?php
/**
 * Orders - My Account
 *
 * @package GStore
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_account_orders', $has_orders );

if ( $has_orders ) :
	$account_orders_columns = array(
		'order-number'   => __( 'Pedido', 'gstore' ),
		'order-date'     => __( 'Data', 'gstore' ),
		'order-status'   => __( 'Status', 'gstore' ),
		'order-tracking' => __( 'Rastreio', 'gstore' ),
		'order-total'    => __( 'Total', 'gstore' ),
		'order-actions'  => __( 'Ações', 'gstore' ),
	);
	$sortable_columns       = array(
		'order-number' => 'order_number',
		'order-date'   => 'order_date',
		'order-status' => 'order_status',
	);

	$current_sort = isset( $_GET['gstore_sort'] ) ? wc_clean( wp_unslash( $_GET['gstore_sort'] ) ) : 'order_status'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$current_dir  = isset( $_GET['gstore_dir'] ) ? strtolower( wc_clean( wp_unslash( $_GET['gstore_dir'] ) ) ) : 'desc'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( ! in_array( $current_sort, array( 'order_number', 'order_date', 'order_status' ), true ) ) {
		$current_sort = 'order_status';
	}

	if ( ! in_array( $current_dir, array( 'desc', 'asc' ), true ) ) {
		$current_dir = 'desc';
	}

	$display_order_rows = is_array( $customer_orders->orders ) ? array_values( $customer_orders->orders ) : array();
	$order_cache        = array();

	$get_order_from_row = static function( $row ) use ( &$order_cache ) {
		if ( $row instanceof WC_Order ) {
			return $row;
		}

		$order_id = absint( $row );
		if ( $order_id <= 0 ) {
			return false;
		}

		if ( isset( $order_cache[ $order_id ] ) ) {
			return $order_cache[ $order_id ];
		}

		$order = wc_get_order( $order_id );
		if ( $order instanceof WC_Order ) {
			$order_cache[ $order_id ] = $order;
			return $order;
		}

		return false;
	};

	$get_order_number_sort_value = static function( WC_Order $order ) {
		$order_number = (string) $order->get_order_number();
		$digits_only  = preg_replace( '/\D+/', '', $order_number );

		if ( '' !== (string) $digits_only ) {
			return absint( $digits_only );
		}

		return (int) $order->get_id();
	};

	$get_status_priority = static function( WC_Order $order ) {
		$status = $order->get_status();

		if ( 'cancelled' === $status && function_exists( 'gstore_my_account_order_has_payment_evidence' ) && gstore_my_account_order_has_payment_evidence( $order ) ) {
			return 95; // "Pago/Confirmado" (cancelled com evidência) fica próximo dos pagos.
		}

		switch ( $status ) {
			case 'processing':
				return 100;
			case 'completed':
				return 90;
			case 'on-hold':
				return 80;
			case 'pending':
				return 70;
			case 'failed':
				return 40;
			case 'refunded':
				return 30;
			case 'cancelled':
				return 0;
			default:
				return 50;
		}
	};

	$get_order_tracking = static function( WC_Order $order ) {
		$tracking_meta_keys = array(
			'_wc_shipment_tracking_items',
			'_tracking_number',
			'tracking_number',
			'_tracking_code',
			'tracking_code',
			'_correios_tracking_code',
			'_correios_tracking_number',
			'_codigo_rastreio',
			'codigo_rastreio',
			'_melhor_envio_tracking',
			'_melhor_envio_tracking_code',
			'_aftership_tracking_number',
		);

		foreach ( $tracking_meta_keys as $meta_key ) {
			$raw_value = $order->get_meta( $meta_key, true );

			if ( empty( $raw_value ) ) {
				continue;
			}

			if ( is_array( $raw_value ) ) {
				foreach ( $raw_value as $item ) {
					if ( ! is_array( $item ) ) {
						continue;
					}

					$tracking_number = '';
					if ( ! empty( $item['tracking_number'] ) ) {
						$tracking_number = (string) $item['tracking_number'];
					} elseif ( ! empty( $item['tracking_code'] ) ) {
						$tracking_number = (string) $item['tracking_code'];
					}

					$tracking_number = trim( wp_strip_all_tags( $tracking_number ) );
					if ( '' === $tracking_number ) {
						continue;
					}

					$tracking_url = '';
					if ( ! empty( $item['custom_tracking_link'] ) ) {
						$tracking_url = (string) $item['custom_tracking_link'];
					} elseif ( ! empty( $item['tracking_link'] ) ) {
						$tracking_url = (string) $item['tracking_link'];
					}

					return array(
						'label' => $tracking_number,
						'url'   => esc_url_raw( $tracking_url ),
					);
				}

				continue;
			}

			$tracking_number = trim( wp_strip_all_tags( (string) $raw_value ) );
			if ( '' !== $tracking_number ) {
				return array(
					'label' => $tracking_number,
					'url'   => '',
				);
			}
		}

		return false;
	};

	if ( count( $display_order_rows ) > 1 ) {
		usort(
			$display_order_rows,
			static function( $left_row, $right_row ) use ( $current_sort, $current_dir, $get_order_from_row, $get_order_number_sort_value, $get_status_priority ) {
				$left_order  = $get_order_from_row( $left_row );
				$right_order = $get_order_from_row( $right_row );

				if ( ! $left_order instanceof WC_Order && ! $right_order instanceof WC_Order ) {
					return 0;
				}
				if ( ! $left_order instanceof WC_Order ) {
					return 1;
				}
				if ( ! $right_order instanceof WC_Order ) {
					return -1;
				}

				$left_date  = $left_order->get_date_created();
				$right_date = $right_order->get_date_created();
				$left_ts    = $left_date ? (int) $left_date->getTimestamp() : 0;
				$right_ts   = $right_date ? (int) $right_date->getTimestamp() : 0;

				switch ( $current_sort ) {
					case 'order_number':
						$comparison = $get_order_number_sort_value( $left_order ) <=> $get_order_number_sort_value( $right_order );
						break;
					case 'order_date':
						$comparison = $left_ts <=> $right_ts;
						break;
					case 'order_status':
					default:
						$comparison = $get_status_priority( $left_order ) <=> $get_status_priority( $right_order );
						break;
				}

				if ( 0 === $comparison ) {
					$comparison = $left_ts <=> $right_ts;
				}

				if ( 0 === $comparison ) {
					$comparison = (int) $left_order->get_id() <=> (int) $right_order->get_id();
				}

				return 'asc' === $current_dir ? $comparison : ( $comparison * -1 );
			}
		);
	}
	?>
	<table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table">
		<thead>
			<tr>
				<?php foreach ( $account_orders_columns as $column_id => $column_name ) : ?>
					<?php
					$is_sortable     = isset( $sortable_columns[ $column_id ] );
					$column_sort_key = $is_sortable ? $sortable_columns[ $column_id ] : '';
					$is_active_sort  = $is_sortable && $current_sort === $column_sort_key;
					$next_dir        = ( $is_active_sort && 'desc' === $current_dir ) ? 'asc' : 'desc';
					$sort_indicator  = $is_active_sort ? ( 'desc' === $current_dir ? '↓' : '↑' ) : '↕';
					$aria_sort       = 'none';

					if ( $is_active_sort ) {
						$aria_sort = 'desc' === $current_dir ? 'descending' : 'ascending';
					}
					?>
					<th class="woocommerce-orders-table__header woocommerce-orders-table__header-<?php echo esc_attr( $column_id ); ?>" aria-sort="<?php echo esc_attr( $aria_sort ); ?>">
						<?php if ( $is_sortable ) : ?>
							<a
								href="<?php echo esc_url( add_query_arg( array( 'gstore_sort' => $column_sort_key, 'gstore_dir' => $next_dir ) ) ); ?>"
								class="gstore-orders-sort-link"
								style="color:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:.35em;"
							>
								<span><?php echo esc_html( $column_name ); ?></span>
								<span aria-hidden="true"><?php echo esc_html( $sort_indicator ); ?></span>
							</a>
						<?php else : ?>
							<?php echo esc_html( $column_name ); ?>
						<?php endif; ?>
					</th>
				<?php endforeach; ?>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $display_order_rows as $customer_order ) : ?>
				<?php
				$order = $get_order_from_row( $customer_order );
				if ( ! $order instanceof WC_Order ) {
					continue;
				}
				$item_count = $order->get_item_count() - $order->get_item_count_refunded();
				?>
				<tr class="woocommerce-orders-table__row woocommerce-orders-table__row--status-<?php echo esc_attr( $order->get_status() ); ?> order">
					<?php foreach ( $account_orders_columns as $column_id => $column_name ) : ?>
						<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-<?php echo esc_attr( $column_id ); ?>" data-title="<?php echo esc_attr( $column_name ); ?>">
							<?php if ( has_action( 'woocommerce_my_account_my_orders_column_' . $column_id ) ) : ?>
								<?php do_action( 'woocommerce_my_account_my_orders_column_' . $column_id, $order ); ?>
							<?php elseif ( 'order-number' === $column_id ) : ?>
								<a href="<?php echo esc_url( $order->get_view_order_url() ); ?>">
									<?php echo esc_html( _x( '#', 'hash before order number', 'woocommerce' ) . $order->get_order_number() ); ?>
								</a>
							<?php elseif ( 'order-date' === $column_id ) : ?>
								<time datetime="<?php echo esc_attr( $order->get_date_created()->date( 'c' ) ); ?>">
									<?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?>
								</time>
							<?php elseif ( 'order-status' === $column_id ) : ?>
								<?php
								$status_label = function_exists( 'gstore_my_account_get_orders_tab_status_label' )
									? gstore_my_account_get_orders_tab_status_label( $order )
									: wc_get_order_status_name( $order->get_status() );
								?>
								<span class="gstore-orders-status gstore-orders-status--<?php echo esc_attr( $order->get_status() ); ?>">
									<?php echo esc_html( $status_label ); ?>
								</span>
							<?php elseif ( 'order-tracking' === $column_id ) : ?>
								<?php $tracking = $get_order_tracking( $order ); ?>
								<?php if ( is_array( $tracking ) && ! empty( $tracking['label'] ) ) : ?>
									<?php if ( ! empty( $tracking['url'] ) ) : ?>
										<a class="gstore-orders-tracking" href="<?php echo esc_url( $tracking['url'] ); ?>" target="_blank" rel="noopener">
											<?php echo esc_html( $tracking['label'] ); ?>
										</a>
									<?php else : ?>
										<span class="gstore-orders-tracking"><?php echo esc_html( $tracking['label'] ); ?></span>
									<?php endif; ?>
								<?php else : ?>
									<span class="gstore-orders-tracking is-empty"><?php esc_html_e( 'Não Disponível', 'gstore' ); ?></span>
								<?php endif; ?>
							<?php elseif ( 'order-total' === $column_id ) : ?>
								<?php
								printf(
									/* translators: 1: formatted order total 2: total order items */
									esc_html( _n( '%1$s de %2$s item', '%1$s de %2$s itens', $item_count, 'woocommerce' ) ),
									wp_kses_post( $order->get_formatted_order_total() ),
									esc_html( $item_count )
								);
								?>
							<?php elseif ( 'order-actions' === $column_id ) : ?>
								<?php
								$actions = wc_get_account_orders_actions( $order );
								if ( ! empty( $actions ) ) :
									foreach ( $actions as $key => $action ) :
										?>
										<a href="<?php echo esc_url( $action['url'] ); ?>" class="woocommerce-button button <?php echo esc_attr( $key ); ?>">
											<?php echo esc_html( $action['name'] ); ?>
										</a>
										<?php
									endforeach;
								endif;
								?>
							<?php endif; ?>
						</td>
					<?php endforeach; ?>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php if ( $customer_orders->max_num_pages > 1 ) : ?>
		<?php
		$base  = trailingslashit( wc_get_endpoint_url( 'orders' ) ) . '%#%/';
		$links = paginate_links(
			array(
				'base'      => esc_url_raw( $base ),
				'format'    => '',
				'current'   => max( 1, (int) $current_page ),
				'total'     => (int) $customer_orders->max_num_pages,
				'add_args'  => array(
					'gstore_sort' => $current_sort,
					'gstore_dir'  => $current_dir,
				),
				'prev_text' => '&larr;',
				'next_text' => '&rarr;',
				'type'      => 'list',
			)
		);
		?>
		<?php if ( $links ) : ?>
			<nav class="woocommerce-pagination woocommerce-pagination--with-numbers">
				<?php echo wp_kses_post( $links ); ?>
			</nav>
		<?php endif; ?>
	<?php endif; ?>

<?php else : ?>
	<?php wc_print_notice( esc_html__( 'Nenhum pedido foi feito ainda.', 'woocommerce' ) ); ?>
<?php endif; ?>

<?php do_action( 'woocommerce_after_account_orders', $has_orders ); ?>
