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
	?>
	<table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table">
		<thead>
			<tr>
				<?php foreach ( wc_get_account_orders_columns() as $column_id => $column_name ) : ?>
					<th class="woocommerce-orders-table__header woocommerce-orders-table__header-<?php echo esc_attr( $column_id ); ?>">
						<?php echo esc_html( $column_name ); ?>
					</th>
				<?php endforeach; ?>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $customer_orders->orders as $customer_order ) : ?>
				<?php
				$order      = wc_get_order( $customer_order );
				$item_count = $order->get_item_count() - $order->get_item_count_refunded();
				?>
				<tr class="woocommerce-orders-table__row woocommerce-orders-table__row--status-<?php echo esc_attr( $order->get_status() ); ?> order">
					<?php foreach ( wc_get_account_orders_columns() as $column_id => $column_name ) : ?>
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
								<?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?>
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
