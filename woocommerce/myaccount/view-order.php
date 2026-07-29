<?php
/**
 * View Order - My Account
 *
 * Template customizado com timeline de fulfillment e upload de documentos.
 *
 * @package GStore
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

$order_id = $order->get_id();

// Dados de fulfillment (lê direto do meta para funcionar sem o plugin).
$fulfillment_stage     = gstore_get_order_fulfillment_stage( $order );
$fulfillment_documents = gstore_get_order_fulfillment_documents( $order );
$required_docs         = gstore_get_order_required_documents( $order );
$doc_profile           = gstore_get_order_doc_profile( $order );

$has_fulfillment = ! empty( $fulfillment_stage );

// Definição das etapas.
$stages = array(
	'processando_pagamento'    => 'Processando Pagamento',
	'pagamento_confirmado'     => 'Pagamento Confirmado',
	'aguardando_documentacao'  => 'Aguardando Documentação',
	'processando_documentacao' => 'Processando Documentação',
	'preparando_entrega'       => 'Preparando Entrega',
	'enviado'                  => 'Enviado',
);

$stage_keys   = array_keys( $stages );
$current_idx  = $has_fulfillment ? array_search( $fulfillment_stage, $stage_keys, true ) : -1;

// Documentos já enviados agrupados por tipo (múltiplos por tipo permitidos).
$uploaded_by_type = array();
$total_docs_count = 0;
$max_docs         = 5;
foreach ( $fulfillment_documents as $doc ) {
	$uploaded_by_type[ $doc['doc_type'] ][] = $doc;
	if ( 'rejected' !== $doc['status'] ) {
		$total_docs_count++;
	}
}
$can_upload_more  = $total_docs_count < $max_docs;
$order_date       = $order->get_date_created();
$order_item_count = max( 0, $order->get_item_count() - $order->get_item_count_refunded() );
$order_status     = function_exists( 'gstore_my_account_get_orders_tab_status_label' )
	? gstore_my_account_get_orders_tab_status_label( $order )
	: wc_get_order_status_name( $order->get_status() );
?>

<div class="gstore-view-order">
	<a class="gstore-view-order__back" href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>">
		<span aria-hidden="true">&larr;</span>
		<?php esc_html_e( 'Voltar aos pedidos', 'gstore' ); ?>
	</a>

	<header class="gstore-view-order__hero" aria-labelledby="gstore-view-order-title">
		<div class="gstore-view-order__hero-copy">
			<span class="gstore-account-page-header__eyebrow"><?php esc_html_e( 'Pedido', 'gstore' ); ?></span>
			<h1 id="gstore-view-order-title" class="gstore-view-order__title">
				<?php
				printf(
					/* translators: %s: order number. */
					esc_html__( '#%s', 'gstore' ),
					esc_html( $order->get_order_number() )
				);
				?>
			</h1>
			<p class="gstore-view-order__meta">
				<?php if ( $order_date ) : ?>
					<?php
					printf(
						/* translators: 1: order date. 2: item count. */
						esc_html( _n( 'Realizado em %1$s · %2$s item', 'Realizado em %1$s · %2$s itens', $order_item_count, 'gstore' ) ),
						esc_html( wc_format_datetime( $order_date ) ),
						esc_html( $order_item_count )
					);
					?>
				<?php endif; ?>
			</p>
		</div>
		<dl class="gstore-view-order__summary">
			<div>
				<dt><?php esc_html_e( 'Status', 'gstore' ); ?></dt>
				<dd>
					<span class="gstore-orders-status gstore-orders-status--<?php echo esc_attr( $order->get_status() ); ?>">
						<?php echo esc_html( $order_status ); ?>
					</span>
				</dd>
			</div>
			<div>
				<dt><?php esc_html_e( 'Total do pedido', 'gstore' ); ?></dt>
				<dd><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></dd>
			</div>
		</dl>
	</header>

	<?php if ( $has_fulfillment ) : ?>
	<!-- ════════════ Timeline ════════════ -->
	<div class="gstore-fulfillment-timeline" data-current-stage="<?php echo esc_attr( $fulfillment_stage ); ?>">
		<?php
		$i = 0;
		foreach ( $stages as $slug => $label ) :
			$is_completed = $i < $current_idx;
			$is_current   = $i === $current_idx;
			$state_class  = $is_completed ? 'is-completed' : ( $is_current ? 'is-current' : 'is-pending' );
			?>
			<div class="gstore-fulfillment-timeline__step <?php echo esc_attr( $state_class ); ?>" data-stage="<?php echo esc_attr( $slug ); ?>">
				<div class="gstore-fulfillment-timeline__icon">
					<?php if ( $is_completed ) : ?>
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
					<?php elseif ( $is_current ) : ?>
						<span class="gstore-fulfillment-timeline__pulse"></span>
					<?php else : ?>
						<span class="gstore-fulfillment-timeline__dot"></span>
					<?php endif; ?>
				</div>
				<span class="gstore-fulfillment-timeline__label"><?php echo esc_html( $label ); ?></span>
				<?php if ( $i < count( $stages ) - 1 ) : ?>
					<div class="gstore-fulfillment-timeline__connector <?php echo $is_completed ? 'is-filled' : ''; ?>"></div>
				<?php endif; ?>
			</div>
			<?php
			$i++;
		endforeach;
		?>
	</div>

	<!-- ════════════ Upload de Documentos ════════════ -->
	<?php if ( 'aguardando_documentacao' === $fulfillment_stage ) : ?>
	<div class="gstore-fulfillment-upload" id="gstore-fulfillment-upload"
	     data-order-id="<?php echo esc_attr( $order_id ); ?>"
	     data-max-docs="<?php echo esc_attr( $max_docs ); ?>"
	     data-docs="<?php echo esc_attr( wp_json_encode( $fulfillment_documents ) ); ?>">
		<h3 class="gstore-fulfillment-upload__title">Envie seus documentos</h3>
		<p class="gstore-fulfillment-upload__desc">
			Para prosseguir com seu pedido, precisamos da sua documentação.
			Consulte os documentos necessários na nossa
			<a href="<?php echo esc_url( home_url( '/informativo/' ) ); ?>" target="_blank" rel="noopener noreferrer" class="gstore-fulfillment-upload__link">página de informações</a>.
			<br>Formatos aceitos: <strong>PDF, PNG ou JPG</strong> (máximo 10 MB por arquivo, até <?php echo esc_html( $max_docs ); ?> documentos).
		</p>

		<!-- JS renderiza a lista de docs + dropzone aqui -->
		<div id="gstore-fulfillment-docs-list"></div>
		<div id="gstore-fulfillment-dropzone-area"></div>
	</div>
	<?php endif; ?>

	<!-- Documentos já enviados (visível em todas as etapas, se houver) -->
	<?php if ( $has_fulfillment && ! empty( $fulfillment_documents ) && 'aguardando_documentacao' !== $fulfillment_stage ) : ?>
	<div class="gstore-fulfillment-docs-summary">
		<h3>Documentos Enviados</h3>
		<div class="gstore-fulfillment-docs-summary__list">
			<?php foreach ( $fulfillment_documents as $doc ) :
				$status_labels = array(
					'pending'  => 'Pendente',
					'approved' => 'Aprovado',
					'rejected' => 'Rejeitado',
				);
				?>
				<div class="gstore-fulfillment-docs-summary__item">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
					<span class="gstore-fulfillment-docs-summary__name"><?php echo esc_html( $doc['label'] ); ?></span>
					<span class="gstore-fulfillment-upload__status-badge gstore-fulfillment-upload__status-badge--<?php echo esc_attr( $doc['status'] ); ?>">
						<?php echo esc_html( $status_labels[ $doc['status'] ] ?? $doc['status'] ); ?>
					</span>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php endif; ?>

	<?php endif; /* has_fulfillment */ ?>

	<!-- ════════════ Detalhes do pedido (padrão WooCommerce) ════════════ -->
	<div class="gstore-view-order__details">
		<?php
		/**
		 * Informações do pedido.
		 */
		do_action( 'woocommerce_view_order', $order_id );
		?>
	</div>

</div>
