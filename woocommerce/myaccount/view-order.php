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
$can_upload_more = $total_docs_count < $max_docs;
?>

<div class="gstore-view-order">

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
