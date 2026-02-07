<?php
/**
 * Lista de aeroportos por UF para a página informativo (pós-venda) - frete aéreo.
 * Retorna array flat para consumo no JavaScript.
 *
 * @return array Lista de aeroportos com chaves: uf, city, name, iata
 */
function gstore_get_informativo_airports_flat() {
	$airports_by_uf = array(
		'AC' => array(
			array( 'city' => 'Rio Branco', 'name' => 'Plácido de Castro', 'iata' => 'RBR' ),
			array( 'city' => 'Cruzeiro do Sul', 'name' => 'Cruzeiro do Sul', 'iata' => 'CZS' ),
		),
		'AL' => array(
			array( 'city' => 'Maceió', 'name' => 'Zumbi dos Palmares', 'iata' => 'MCZ' ),
			array( 'city' => 'Arapiraca', 'name' => 'Arapiraca', 'iata' => 'APQ' ),
		),
		'AM' => array(
			array( 'city' => 'Manaus', 'name' => 'Eduardo Gomes', 'iata' => 'MAO' ),
			array( 'city' => 'Tefé', 'name' => 'Tefé', 'iata' => 'TFF' ),
			array( 'city' => 'Tabatinga', 'name' => 'Tabatinga', 'iata' => 'TBT' ),
		),
		'AP' => array(
			array( 'city' => 'Macapá', 'name' => 'Alberto Alcolumbre', 'iata' => 'MCP' ),
		),
		'BA' => array(
			array( 'city' => 'Salvador', 'name' => 'Dep. Luís Eduardo Magalhães', 'iata' => 'SSA' ),
			array( 'city' => 'Porto Seguro', 'name' => 'Porto Seguro', 'iata' => 'BPS' ),
			array( 'city' => 'Ilhéus', 'name' => 'Jorge Amado', 'iata' => 'IOS' ),
			array( 'city' => 'Vitória da Conquista', 'name' => 'Glauber Rocha', 'iata' => 'VDC' ),
		),
		'CE' => array(
			array( 'city' => 'Fortaleza', 'name' => 'Pinto Martins', 'iata' => 'FOR' ),
			array( 'city' => 'Juazeiro do Norte', 'name' => 'Orlando Bezerra', 'iata' => 'JDO' ),
			array( 'city' => 'Jericoacoara', 'name' => 'Jericoacoara', 'iata' => 'JJD' ),
		),
		'DF' => array(
			array( 'city' => 'Brasília', 'name' => 'Pres. Juscelino Kubitschek', 'iata' => 'BSB' ),
		),
		'ES' => array(
			array( 'city' => 'Vitória', 'name' => 'Eurico de Aguiar Salles', 'iata' => 'VIX' ),
		),
		'GO' => array(
			array( 'city' => 'Goiânia', 'name' => 'Santa Genoveva', 'iata' => 'GYN' ),
			array( 'city' => 'Caldas Novas', 'name' => 'Caldas Novas', 'iata' => 'CLV' ),
			array( 'city' => 'Rio Verde', 'name' => 'Rio Verde', 'iata' => 'RVD' ),
		),
		'MA' => array(
			array( 'city' => 'São Luís', 'name' => 'Marechal Cunha Machado', 'iata' => 'SLZ' ),
			array( 'city' => 'Imperatriz', 'name' => 'Pref. Renato Moreira', 'iata' => 'IMP' ),
		),
		'MG' => array(
			array( 'city' => 'Belo Horizonte', 'name' => 'Confins', 'iata' => 'CNF' ),
			array( 'city' => 'Belo Horizonte', 'name' => 'Pampulha', 'iata' => 'PLU' ),
			array( 'city' => 'Uberlândia', 'name' => 'Uberlândia', 'iata' => 'UDI' ),
			array( 'city' => 'Montes Claros', 'name' => 'Montes Claros', 'iata' => 'MOC' ),
		),
		'MS' => array(
			array( 'city' => 'Campo Grande', 'name' => 'Campo Grande', 'iata' => 'CGR' ),
			array( 'city' => 'Dourados', 'name' => 'Dourados', 'iata' => 'DOU' ),
			array( 'city' => 'Corumbá', 'name' => 'Corumbá', 'iata' => 'CMG' ),
		),
		'MT' => array(
			array( 'city' => 'Cuiabá', 'name' => 'Marechal Rondon', 'iata' => 'CGB' ),
			array( 'city' => 'Sinop', 'name' => 'Sinop', 'iata' => 'OPS' ),
			array( 'city' => 'Rondonópolis', 'name' => 'Rondonópolis', 'iata' => 'ROO' ),
		),
		'PA' => array(
			array( 'city' => 'Belém', 'name' => 'Val-de-Cans', 'iata' => 'BEL' ),
			array( 'city' => 'Santarém', 'name' => 'Santarém', 'iata' => 'STM' ),
			array( 'city' => 'Marabá', 'name' => 'Marabá', 'iata' => 'MAB' ),
			array( 'city' => 'Altamira', 'name' => 'Altamira', 'iata' => 'ATM' ),
		),
		'PB' => array(
			array( 'city' => 'João Pessoa', 'name' => 'Pres. Castro Pinto', 'iata' => 'JPA' ),
			array( 'city' => 'Campina Grande', 'name' => 'João Suassuna', 'iata' => 'CPV' ),
		),
		'PE' => array(
			array( 'city' => 'Recife', 'name' => 'Guararapes', 'iata' => 'REC' ),
			array( 'city' => 'Fernando de Noronha', 'name' => 'Noronha', 'iata' => 'FEN' ),
			array( 'city' => 'Petrolina', 'name' => 'Petrolina', 'iata' => 'PNZ' ),
		),
		'PI' => array(
			array( 'city' => 'Teresina', 'name' => 'Sen. Petrônio Portella', 'iata' => 'THE' ),
			array( 'city' => 'Parnaíba', 'name' => 'Parnaíba', 'iata' => 'PHB' ),
		),
		'PR' => array(
			array( 'city' => 'Curitiba', 'name' => 'Afonso Pena', 'iata' => 'CWB' ),
			array( 'city' => 'Foz do Iguaçu', 'name' => 'Cataratas', 'iata' => 'IGU' ),
			array( 'city' => 'Londrina', 'name' => 'Londrina', 'iata' => 'LDB' ),
			array( 'city' => 'Maringá', 'name' => 'Maringá', 'iata' => 'MGF' ),
		),
		'RJ' => array(
			array( 'city' => 'Rio de Janeiro', 'name' => 'Galeão', 'iata' => 'GIG' ),
			array( 'city' => 'Rio de Janeiro', 'name' => 'Santos Dumont', 'iata' => 'SDU' ),
			array( 'city' => 'Cabo Frio', 'name' => 'Cabo Frio', 'iata' => 'CFB' ),
		),
		'RN' => array(
			array( 'city' => 'Natal', 'name' => 'Governador Aluízio Alves', 'iata' => 'NAT' ),
			array( 'city' => 'Mossoró', 'name' => 'Mossoró', 'iata' => 'MVF' ),
		),
		'RO' => array(
			array( 'city' => 'Porto Velho', 'name' => 'Governador Jorge Teixeira', 'iata' => 'PVH' ),
			array( 'city' => 'Vilhena', 'name' => 'Vilhena', 'iata' => 'BVH' ),
		),
		'RR' => array(
			array( 'city' => 'Boa Vista', 'name' => 'Boa Vista', 'iata' => 'BVB' ),
		),
		'RS' => array(
			array( 'city' => 'Porto Alegre', 'name' => 'Salgado Filho', 'iata' => 'POA' ),
			array( 'city' => 'Caxias do Sul', 'name' => 'Caxias do Sul', 'iata' => 'CXJ' ),
			array( 'city' => 'Passo Fundo', 'name' => 'Passo Fundo', 'iata' => 'PFB' ),
		),
		'SC' => array(
			array( 'city' => 'Florianópolis', 'name' => 'Hercílio Luz', 'iata' => 'FLN' ),
			array( 'city' => 'Navegantes', 'name' => 'Navegantes', 'iata' => 'NVT' ),
			array( 'city' => 'Joinville', 'name' => 'Joinville', 'iata' => 'JOI' ),
			array( 'city' => 'Chapecó', 'name' => 'Chapecó', 'iata' => 'XAP' ),
		),
		'SE' => array(
			array( 'city' => 'Aracaju', 'name' => 'Santa Maria', 'iata' => 'AJU' ),
		),
		'SP' => array(
			array( 'city' => 'São Paulo', 'name' => 'Guarulhos', 'iata' => 'GRU' ),
			array( 'city' => 'São Paulo', 'name' => 'Congonhas', 'iata' => 'CGH' ),
			array( 'city' => 'Campinas', 'name' => 'Viracopos', 'iata' => 'VCP' ),
			array( 'city' => 'Ribeirão Preto', 'name' => 'Leite Lopes', 'iata' => 'RAO' ),
		),
		'TO' => array(
			array( 'city' => 'Palmas', 'name' => 'Palmas', 'iata' => 'PMW' ),
			array( 'city' => 'Araguaína', 'name' => 'Araguaína', 'iata' => 'AUX' ),
			array( 'city' => 'Gurupi', 'name' => 'Gurupi', 'iata' => 'GRP' ),
		),
	);

	$flat = array();
	foreach ( $airports_by_uf as $uf => $list ) {
		foreach ( $list as $a ) {
			$flat[] = array(
				'uf'   => $uf,
				'city' => $a['city'],
				'name' => $a['name'],
				'iata' => $a['iata'],
			);
		}
	}
	return $flat;
}
