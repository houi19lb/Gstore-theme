jQuery(document).ready(function ($) {
    // Função para limpar formulário de endereço
    function limpa_formulário_cep() {
        $("#billing_address_1").val("");
        $("#billing_neighborhood").val("");
        $("#billing_city").val("");
        $("#billing_state").val("").trigger("change");
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
                        alert("CEP não encontrado.");
                    }
                });
            } else {
                // cep é inválido.
                limpa_formulário_cep();
                alert("Formato de CEP inválido.");
            }
        } else {
            // cep sem valor, limpa formulário.
            limpa_formulário_cep();
        }
    });
});
