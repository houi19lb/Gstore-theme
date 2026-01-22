	jQuery(document).ready(function ($) {
		// Função para limpar formulário de endereço
		function limpa_formulário_cep() {
			$("#billing_address_1").val("");
			$("#billing_neighborhood").val("");
			$("#billing_city").val("");
			$("#billing_state").val("").trigger("change");
		}

		function resolveShippingCalculator() {
			const $calculator = $('.gstore-shipping-calculator');
			if (!$calculator.length) {
				return null;
			}
			return $calculator;
		}

		function showShippingError(message) {
			const $calculator = resolveShippingCalculator();
			if (!$calculator) {
				return;
			}
			const $error = $calculator.find('.gstore-shipping-calculator__error');
			if ($error.length) {
				$error.html('<i class="fa-solid fa-exclamation-circle" aria-hidden="true"></i> ' + message);
				$error.addClass('is-visible');
			}
			$calculator.addClass('has-error');
			$calculator.find('.gstore-shipping-calculator__cep').addClass('has-error');
		}

		function clearShippingError() {
			const $calculator = resolveShippingCalculator();
			if (!$calculator) {
				return;
			}
			const $error = $calculator.find('.gstore-shipping-calculator__error');
			if ($error.length) {
				$error.removeClass('is-visible').html('');
			}
			$calculator.removeClass('has-error');
			$calculator.find('.gstore-shipping-calculator__cep').removeClass('has-error');
		}

		// Quando o campo cep perde o foco.
		$("#billing_postcode").blur(function () {
			// Nova variável "cep" somente com dígitos.
			var cep = $(this).val().replace(/\D/g, '');

			// Verifica se campo cep possui valor informado.
			if (cep != "") {
				// Expressão regular para validar o CEP.
				var validacep = /^[0-9]{8}$/;

				// Valida o formato do CEP.
				if (validacep.test(cep)) {
					clearShippingError();

					// Preenche os campos com "..." enquanto consulta webservice.
					$("#billing_address_1").val("...");
					$("#billing_neighborhood").val("...");
					$("#billing_city").val("...");
					$("#billing_state").val("...");

					// Consulta o webservice viacep.com.br/
					$.getJSON("https://viacep.com.br/ws/" + cep + "/json/?callback=?", function (dados) {
						if (!("erro" in dados)) {
							// Atualiza os campos com os valores da consulta.
							$("#billing_address_1").val(dados.logradouro);
							$("#billing_neighborhood").val(dados.bairro);
							$("#billing_city").val(dados.localidade);
							$("#billing_state").val(dados.uf).trigger("change"); // Trigger change para atualizar o select do estado

							// Foca no número após preencher
							$("#billing_number").focus();
							clearShippingError();
						} else {
							// CEP pesquisado não foi encontrado.
							limpa_formulário_cep();
							showShippingError("CEP não encontrado.");
						}
					});
				} else {
					// cep é inválido.
					limpa_formulário_cep();
					showShippingError("Formato de CEP inválido.");
				}
			} else {
				// cep sem valor, limpa formulário.
				limpa_formulário_cep();
				clearShippingError();
			}
		});
	});
