jQuery(document).ready(function ($) {
    // Função para limpar formulário de endereço
    function limpa_formulário_cep() {
        $("#billing_address_1").val("");
        $("#billing_neighborhood").val("");
        $("#billing_city").val("");
        $("#billing_state").val("").trigger("change");
    }

    // Função para exibir erro de CEP no checkout (sem modal)
    function showCepError(message) {
        // Verifica se estamos no checkout com checkout-steps
        if ($('body').hasClass('woocommerce-checkout') && typeof window.gstoreCheckoutSteps !== 'undefined' && window.gstoreCheckoutSteps.showShippingError) {
            // Usa a função do checkout-steps.js para exibir o erro na área do frete
            window.gstoreCheckoutSteps.showShippingError(message);
        } else {
            // Se não estiver no checkout, apenas limpa o formulário sem mostrar modal
            // (comportamento silencioso para não interromper o fluxo)
        }
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
                    } else {
                        // CEP pesquisado não foi encontrado.
                        limpa_formulário_cep();
                        // Em vez de alert, exibe erro na área do frete
                        showCepError("CEP não encontrado.");
                    }
                });
            } else {
                // cep é inválido.
                limpa_formulário_cep();
                // Em vez de alert, exibe erro na área do frete
                showCepError("Formato de CEP inválido. Por favor, informe um CEP válido com 8 dígitos.");
            }
        } else {
            // cep sem valor, limpa formulário.
            limpa_formulário_cep();
        }
    });
});
