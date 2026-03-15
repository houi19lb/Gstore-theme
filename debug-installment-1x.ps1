param(
    [string]$ThemePath,
    [string]$PluginPath,
    [string]$SiteUrl = '',
    [int]$ProductId = 0,
    [int]$Quantity = 1,
    [int]$Max = 21
)

$ErrorActionPreference = 'Stop'

if (-not $ThemePath) {
    $ThemePath = if ($PSScriptRoot) { $PSScriptRoot } else { (Get-Location).Path }
}

if (-not $PluginPath) {
    $themeParent = Split-Path -Parent $ThemePath
    $PluginPath = Join-Path $themeParent 'GSTORE'
}

function Write-Section {
    param([string]$Title)
    Write-Host ''
    Write-Host ('=' * 80) -ForegroundColor DarkGray
    Write-Host $Title -ForegroundColor Cyan
    Write-Host ('=' * 80) -ForegroundColor DarkGray
}

function Show-Lines {
    param(
        [string]$Path,
        [int]$Start,
        [int]$End
    )

    if (-not (Test-Path $Path)) {
        Write-Host "Arquivo nao encontrado: $Path" -ForegroundColor Red
        return
    }

    $lines = Get-Content -Path $Path
    $safeStart = [Math]::Max(1, $Start)
    $safeEnd = [Math]::Min($End, $lines.Length)

    Write-Host $Path -ForegroundColor Yellow
    for ($i = $safeStart; $i -le $safeEnd; $i++) {
        '{0,5}: {1}' -f $i, $lines[$i - 1]
    }
}

function Show-ReasonSummary {
    Write-Section 'Resumo do motivo'
    Write-Host '1) O tema agora pode puxar a mesma quote do plugin/admin para exibir o parcelamento inicial.' -ForegroundColor White
    Write-Host '2) Regras explicitas de tabela, inclusive 1x, passam a ser respeitadas no produto e no checkout.' -ForegroundColor White
    Write-Host '3) Para modo flat/progressive, o campo min_installments do admin pode ser configurado como 1.' -ForegroundColor White
}

function Show-LocalEvidence {
    Write-Section 'Tema: calculo local do produto'
    Show-Lines -Path (Join-Path $ThemePath 'functions.php') -Start 2794 -End 2965
    Show-Lines -Path (Join-Path $ThemePath 'woocommerce\content-product.php') -Start 48 -End 69
    Show-Lines -Path (Join-Path $ThemePath 'woocommerce\content-single-product.php') -Start 438 -End 458

    Write-Section 'Plugin: configuracao da taxa'
    Show-Lines -Path (Join-Path $PluginPath 'includes\blu\class-gstore-blu-payment-gateway.php') -Start 172 -End 180
    Show-Lines -Path (Join-Path $PluginPath 'includes\blu\class-gstore-blu-payment-gateway.php') -Start 354 -End 360

    Write-Section 'Plugin: quotes e fee do produto'
    Show-Lines -Path (Join-Path $PluginPath 'includes\blu\class-gstore-blu-checkout-handler.php') -Start 1289 -End 1365
    Show-Lines -Path (Join-Path $PluginPath 'includes\blu\class-gstore-blu-checkout-handler.php') -Start 3143 -End 3414

    Write-Section 'Tema: como o cliente ve as parcelas'
    Show-Lines -Path (Join-Path $ThemePath 'assets\js\single-product.js') -Start 178 -End 231
    Show-Lines -Path (Join-Path $ThemePath 'assets\js\product-card.js') -Start 189 -End 309
}

function Get-QuoteValue {
    param(
        [object]$Quotes,
        [string]$Key
    )

    if ($null -eq $Quotes) {
        return $null
    }

    $prop = $Quotes.PSObject.Properties | Where-Object { $_.Name -eq $Key } | Select-Object -First 1
    if ($null -ne $prop) {
        return $prop.Value
    }

    return $null
}

function Test-RemoteQuote {
    param(
        [string]$BaseUrl,
        [int]$RemoteProductId,
        [int]$RemoteQuantity,
        [int]$RemoteMax
    )

    if ([string]::IsNullOrWhiteSpace($BaseUrl) -or $RemoteProductId -le 0) {
        return
    }

    Write-Section 'Consulta remota do endpoint AJAX'

    $trimmed = $BaseUrl.TrimEnd('/')
    $ajaxUrl = "$trimmed/wp-admin/admin-ajax.php"
    $body = @{
        action     = 'gstore_blu_get_product_installment_quotes'
        product_id = $RemoteProductId
        quantity   = [Math]::Max(1, $RemoteQuantity)
        max        = [Math]::Max(1, $RemoteMax)
    }

    Write-Host "POST $ajaxUrl" -ForegroundColor Yellow
    Write-Host ("Body: action={0}, product_id={1}, quantity={2}, max={3}" -f $body.action, $body.product_id, $body.quantity, $body.max) -ForegroundColor DarkGray

    $response = Invoke-RestMethod -Method Post -Uri $ajaxUrl -Body $body -ContentType 'application/x-www-form-urlencoded'

    if (-not $response.success) {
        $message = $response.data.message
        if (-not $message) {
            $message = 'Resposta sem success=true.'
        }
        throw "Falha no endpoint: $message"
    }

    $quotes = $response.data.quotes
    $quote1 = Get-QuoteValue -Quotes $quotes -Key '1'
    $quote2 = Get-QuoteValue -Quotes $quotes -Key '2'

    if ($null -eq $quote1) {
        Write-Host 'Quote 1x nao veio na resposta.' -ForegroundColor Red
    } else {
        Write-Host ("1x => total: {0} | parcela: {1}" -f $quote1.total_text, $quote1.per_installment_text) -ForegroundColor Green
    }

    if ($null -eq $quote2) {
        Write-Host 'Quote 2x nao veio na resposta.' -ForegroundColor Yellow
    } else {
        Write-Host ("2x => total: {0} | parcela: {1}" -f $quote2.total_text, $quote2.per_installment_text) -ForegroundColor Green
    }

    if ($null -ne $quote1 -and $null -ne $quote2) {
        $total1 = [double]$quote1.total_raw
        $total2 = [double]$quote2.total_raw
        $diff = [Math]::Round(($total2 - $total1), 2)

        Write-Host ("Diferenca total 2x - 1x: R$ {0}" -f $diff.ToString('N2')) -ForegroundColor Cyan
        if ($diff -gt 0) {
            Write-Host 'Se 1x ja vier acima do preco base ou se a tabela do admin tiver 1=..., a regra de 1x esta sendo respeitada.' -ForegroundColor White
        } else {
            Write-Host 'Nao houve aumento do total entre 1x e 2x nesta resposta.' -ForegroundColor White
        }
    }
}

Show-ReasonSummary
Show-LocalEvidence
Test-RemoteQuote -BaseUrl $SiteUrl -RemoteProductId $ProductId -RemoteQuantity $Quantity -RemoteMax $Max

Write-Host ''
Write-Host 'Uso local:' -ForegroundColor Cyan
Write-Host '  .\debug-installment-1x.ps1' -ForegroundColor White
Write-Host 'Uso com produto real:' -ForegroundColor Cyan
Write-Host '  .\debug-installment-1x.ps1 -SiteUrl "https://seusite.com" -ProductId 12345' -ForegroundColor White
