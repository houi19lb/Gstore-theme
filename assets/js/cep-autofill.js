jQuery(document).ready(function ($) {
    // Função para limpar formulário de endereço
    function limpa_formulário_cep() {
        $("#billing_address_1").val("");
        $("#billing_neighborhood").val("");
        $("#billing_city").val("");
        $("#billing_state").val("").trigger("change");
    }
    
    // Função para mostrar erro no campo CEP e na área de frete
    function mostrarErroCep(mensagem) {
        var $cepField = $("#billing_postcode");
        var $fieldWrapper = $cepField.closest('.form-row, .woocommerce-input-wrapper').parent();
        
        // Adiciona classe de erro no campo
        $fieldWrapper.addClass('woocommerce-invalid woocommerce-invalid-required-field');
        
        // Remove mensagem de erro anterior se existir
        $fieldWrapper.find('.gstore-cep-error-message').remove();
        
        // Adiciona mensagem de erro abaixo do campo
        var $errorMsg = $('<div class="gstore-cep-error-message" style="color: #991b1b; font-size: 0.875rem; margin-top: 5px;"><i class="fa-solid fa-circle-exclamation"></i> ' + mensagem + '</div>');
        $fieldWrapper.append($errorMsg);
        
        // Mostra erro na área de frete se a função estiver disponível
        if (typeof window !== 'undefined' && typeof window.gstoreShowShippingError === 'function') {
            window.gstoreShowShippingError(mensagem);
        }
    }
    
    // Função para limpar erros do campo CEP
    function limparErroCep() {
        var $cepField = $("#billing_postcode");
        var $fieldWrapper = $cepField.closest('.form-row, .woocommerce-input-wrapper').parent();
        $fieldWrapper.removeClass('woocommerce-invalid woocommerce-invalid-required-field');
        $fieldWrapper.find('.gstore-cep-error-message').remove();
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
                // Limpa erros anteriores
                limparErroCep();
                
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

                        // Limpa erros ao preencher com sucesso
                        limparErroCep();

                        // Foca no número após preencher
                        $("#billing_number").focus();
                    } else {
                        // CEP pesquisado não foi encontrado.
                        limpa_formulário_cep();
                        mostrarErroCep("CEP não encontrado.");
                    }
                }).fail(function() {
                    // Erro na requisição
                    limpa_formulário_cep();
                    mostrarErroCep("Erro ao consultar CEP. Tente novamente.");
                });
            } else {
                // cep é inválido.
                limpa_formulário_cep();
                mostrarErroCep("Formato de CEP inválido. Informe um CEP com 8 dígitos.");
            }
        } else {
            // cep sem valor, limpa formulário.
            limpa_formulário_cep();
        }
    });
    
    // Limpa erros quando o usuário começa a digitar
    $("#billing_postcode").on('input', function() {
        var cep = $(this).val().replace(/\D/g, '');
        if (cep.length > 0) {
            limparErroCep();
        }
    });
});
