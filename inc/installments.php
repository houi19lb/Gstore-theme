<?php

/**
 * ============================================
 * CÁLCULO DE PARCELAS COM JUROS (BLU)
 * ============================================
 * Taxa padrão: 2.99% a.m. (configurável via filtro)
 * Fórmula: PMT = PV * [i * (1 + i)^n] / [(1 + i)^n - 1]
 */

/**
 * Calcula o valor da parcela com juros compostos.
 *
 * @param float $price         Valor total do produto (presente).
 * @param int   $installments  Número de parcelas.
 * @param float $rate          Taxa de juros mensal (decimal). Padrão: 0.0299 (2.99% a.m.).
 * @return float Valor da parcela.
 */
function gstore_calculate_installment_with_interest( $price, $installments = 12, $rate = null ) {
	if ( $price <= 0 || $installments <= 0 ) {
		return 0;
	}

	// Taxa de juros configurável via filtro. Padrão: 2.99% a.m.
	if ( null === $rate ) {
		$rate = function_exists( 'apply_filters' )
			? apply_filters( 'gstore_blu_installment_rate', 0.0299 )
			: 0.0299;
	}

	// Se taxa for 0, retorna simples divisão (sem juros)
	if ( $rate <= 0 ) {
		return $price / $installments;
	}

	// Fórmula de parcela com juros compostos (Price/PGTO)
	// PMT = PV * [i * (1 + i)^n] / [(1 + i)^n - 1]
	$factor   = pow( 1 + $rate, $installments );
	$pmt      = $price * ( $rate * $factor ) / ( $factor - 1 );

	return $pmt;
}

/**
 * Retorna a taxa de juros atual da Blu.
 *
 * @return float Taxa em decimal (ex: 0.0299 = 2.99% a.m.).
 */
function gstore_get_blu_installment_rate() {
	return function_exists( 'apply_filters' )
		? apply_filters( 'gstore_blu_installment_rate', 0.0299 )
		: 0.0299;
}

/**
 * Retorna a taxa de juros atual da Blu em percentual.
 *
 * @return string Taxa formatada (ex: "2,99%").
 */
function gstore_get_blu_installment_rate_percent() {
	$rate = gstore_get_blu_installment_rate();
	return number_format( $rate * 100, 2, ',', '.' ) . '%';
}
