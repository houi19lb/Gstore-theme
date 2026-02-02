# Sistema de Imagens da Biblioteca - Gstore Theme

## Contexto rápido (LLMs/novos devs)
- Guia para carregar imagens da biblioteca no tema.
- Evita URLs hardcoded e problemas entre ambientes.

Este documento explica como usar as funções helper para carregar imagens da biblioteca de mídia do WordPress nos templates do tema.

## 📋 Visão Geral

O tema Gstore agora suporta carregar imagens diretamente da biblioteca de mídia do WordPress, substituindo URLs hardcoded por funções dinâmicas. Isso garante que as imagens funcionem em qualquer ambiente (desenvolvimento, staging, produção).

## 🎯 Funções Disponíveis

### 1. `gstore_get_image_url( $attachment_id, $size = 'full' )`

Retorna apenas a URL de uma imagem da biblioteca.

**Parâmetros:**
- `$attachment_id` (int): ID da imagem na biblioteca de mídia
- `$size` (string): Tamanho da imagem (thumbnail, medium, large, full, etc.)

**Exemplo:**
```php
$image_url = gstore_get_image_url( 123, 'large' );
// Retorna: https://seusite.com/wp-content/uploads/2025/11/imagem.jpg
```

### 2. `gstore_get_image_tag( $attachment_id, $size = 'full', $alt = '', $attr = array() )`

Retorna a tag `<img>` completa com todos os atributos.

**Parâmetros:**
- `$attachment_id` (int): ID da imagem na biblioteca
- `$size` (string): Tamanho da imagem
- `$alt` (string): Texto alternativo (opcional)
- `$attr` (array): Atributos adicionais (loading, decoding, etc.)

**Exemplo:**
```php
$img_tag = gstore_get_image_tag( 123, 'full', 'Descrição da imagem' );
// Retorna: <img src="..." alt="Descrição da imagem" loading="lazy" decoding="async" />
```

## 🔧 Shortcodes

### `[gstore_image_url id="123" size="full"]`

Retorna apenas a URL da imagem. Útil para usar em atributos `src`.

**Exemplo:**
```html
<img src="[gstore_image_url id='123' size='large']" alt="Minha imagem" />
```

### `[gstore_image id="123" size="full" alt="Descrição"]`

Retorna a tag `<img>` completa.

**Exemplo:**
```
[gstore_image id="123" size="large" alt="Banner promocional"]
```

## 📝 Placeholders em Templates HTML

Para templates HTML (como `parts/home-hero.html`), use placeholders que serão processados automaticamente:

### Formato: `{{gstore_image:ID:size}}`

**Exemplo:**
```html
<!-- URL apenas -->
<img src="{{gstore_image:123:full}}" alt="Banner" />

<!-- Tag completa -->
{{gstore_image_tag:123:large:Descrição do banner}}
```

**Onde:**
- `123` = ID da imagem na biblioteca de mídia
- `full` = Tamanho da imagem (opcional, padrão: full)
- `Descrição` = Texto alternativo (opcional, apenas para tag completa)

## 📂 Arquivos Atualizados

Os seguintes arquivos foram atualizados para usar o novo sistema:

1. **`parts/home-hero.html`**
   - Slides do hero agora usam placeholders
   - **Ação necessária:** Substitua `{{gstore_image:0:full}}` pelos IDs reais das imagens

2. **`templates/page-home.html`**
   - Banner do YouTube agora usa placeholder
   - **Ação necessária:** Substitua `{{gstore_image:0:full}}` pelo ID real da imagem

3. **`templates/home.html`**
   - Banner do YouTube agora usa placeholder
   - **Ação necessária:** Substitua `{{gstore_image:0:full}}` pelo ID real da imagem

## ✅ Como Encontrar o ID de uma Imagem

1. Acesse **Mídia > Biblioteca** no painel do WordPress
2. Clique na imagem desejada
3. Na URL do navegador, você verá algo como: `...post.php?post=123&action=edit`
4. O número `123` é o ID da imagem

Ou use este código no console do navegador (na página de edição da mídia):
```javascript
// No console do navegador
wp.media.frame.state().get('selection').first().id
```

## 🔄 Migração de URLs Hardcoded

### Antes:
```html
<img src="http://localhost:10005/wp-content/uploads/2025/11/Slide-1.jpg" alt="Banner" />
```

### Depois:
```html
<img src="{{gstore_image:123:full}}" alt="Banner" />
```

Onde `123` é o ID da imagem na biblioteca.

## ⚠️ Importante

1. **IDs devem ser substituídos:** Os placeholders `{{gstore_image:0:full}}` são apenas exemplos. Você **deve** substituir o `0` pelo ID real da imagem.

2. **Imagens devem estar na biblioteca:** Certifique-se de que as imagens foram enviadas para a biblioteca de mídia do WordPress antes de usar os IDs.

3. **Tamanhos disponíveis:** Use tamanhos padrão do WordPress (thumbnail, medium, large, full) ou tamanhos customizados registrados no tema.

## 🐛 Troubleshooting

### Imagem não aparece?
- Verifique se o ID da imagem está correto
- Confirme que a imagem existe na biblioteca de mídia
- Verifique se o tamanho especificado existe

### Placeholder não é processado?
- Certifique-se de que o filtro `gstore_process_image_placeholders` está ativo
- Verifique se o conteúdo está sendo processado pelo WordPress (não em arquivos HTML estáticos)

## 📚 Exemplos Completos

### Exemplo 1: Hero Slider
```html
<figure class="Gstore-hero-slider__slide">
	<img src="{{gstore_image:456:full}}" alt="Campanha Black Week" loading="lazy" />
</figure>
```

### Exemplo 2: Banner
```html
<figure class="wp-block-image">
	<img src="{{gstore_image:789:large}}" alt="Banner YouTube" />
</figure>
```

### Exemplo 3: Em PHP
```php
<?php
$hero_image_id = 123; // ID da imagem
$hero_image_url = gstore_get_image_url( $hero_image_id, 'full' );
?>
<img src="<?php echo esc_url( $hero_image_url ); ?>" alt="Hero" />
```

## 🔗 Referências

- [WordPress Media Library](https://wordpress.org/support/article/media-library-screen/)
- [wp_get_attachment_image_url()](https://developer.wordpress.org/reference/functions/wp_get_attachment_image_url/)
- [Image Sizes](https://developer.wordpress.org/reference/functions/add_image_size/)

