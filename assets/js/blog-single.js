/**
 * ==========================================
 * BLOG SINGLE POST - Funcionalidades
 * ==========================================
 * Calcula tempo de leitura, implementa compartilhamento social
 * e atualiza breadcrumb dinamicamente.
 */

(function() {
	'use strict';

	// Aguarda o DOM estar pronto
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initBlogSingle);
	} else {
		initBlogSingle();
	}

	function initBlogSingle() {
		calculateReadingTime();
		initSocialShare();
		updateBreadcrumb();
		filterRelatedPosts();
	}

	/**
	 * Calcula o tempo estimado de leitura baseado no conteúdo
	 * Assumindo velocidade média de 200 palavras por minuto
	 */
	function calculateReadingTime() {
		const readingTimeElement = document.querySelector('.Gstore-blog-single-meta__reading-time-text');
		if (!readingTimeElement) {
			return;
		}

		const contentElement = document.querySelector('.Gstore-blog-single-content');
		if (!contentElement) {
			return;
		}

		// Remove elementos que não devem contar (scripts, estilos, etc)
		const clone = contentElement.cloneNode(true);
		const scripts = clone.querySelectorAll('script, style, nav, aside, .wp-block-query');
		scripts.forEach(el => el.remove());

		// Conta palavras no texto
		const text = clone.textContent || clone.innerText || '';
		const words = text.trim().split(/\s+/).filter(word => word.length > 0);
		const wordCount = words.length;

		// Velocidade média: 200 palavras por minuto
		const readingTime = Math.ceil(wordCount / 200);
		const minutes = readingTime === 0 ? 1 : readingTime;

		readingTimeElement.textContent = `${minutes} ${minutes === 1 ? 'min' : 'mins'} de leitura`;
	}

	/**
	 * Inicializa os botões de compartilhamento social
	 */
	function initSocialShare() {
		const shareButtons = document.querySelectorAll('.Gstore-blog-single-share__button');
		if (shareButtons.length === 0) {
			return;
		}

		const currentUrl = encodeURIComponent(window.location.href);
		const currentTitle = encodeURIComponent(document.title);
		const currentDescription = getMetaDescription();

		shareButtons.forEach(button => {
			const shareType = button.getAttribute('data-share');
			if (!shareType) {
				return;
			}

			if (shareType === 'copy') {
				button.addEventListener('click', handleCopyLink);
			} else {
				button.addEventListener('click', (e) => {
					e.preventDefault();
					shareToSocial(shareType, currentUrl, currentTitle, currentDescription);
				});

				// Atualiza o href para permitir abrir em nova aba
				const shareUrl = getShareUrl(shareType, currentUrl, currentTitle, currentDescription);
				if (shareUrl && button.tagName === 'A') {
					button.href = shareUrl;
				}
			}
		});
	}

	/**
	 * Obtém a descrição meta do post
	 */
	function getMetaDescription() {
		const metaDesc = document.querySelector('meta[name="description"]');
		if (metaDesc) {
			return encodeURIComponent(metaDesc.getAttribute('content') || '');
		}

		// Fallback: pega excerpt do post
		const excerpt = document.querySelector('.Gstore-blog-single-content p');
		if (excerpt) {
			return encodeURIComponent(excerpt.textContent.substring(0, 160) || '');
		}

		return '';
	}

	/**
	 * Gera URL de compartilhamento para cada rede social
	 */
	function getShareUrl(platform, url, title, description) {
		const urls = {
			facebook: `https://www.facebook.com/sharer/sharer.php?u=${url}`,
			twitter: `https://twitter.com/intent/tweet?url=${url}&text=${title}`,
			whatsapp: `https://wa.me/?text=${title}%20${url}`,
			linkedin: `https://www.linkedin.com/sharing/share-offsite/?url=${url}`
		};

		return urls[platform] || null;
	}

	/**
	 * Compartilha em rede social
	 */
	function shareToSocial(platform, url, title, description) {
		const shareUrl = getShareUrl(platform, url, title, description);
		if (shareUrl) {
			window.open(shareUrl, '_blank', 'width=600,height=400,menubar=no,toolbar=no,resizable=yes,scrollbars=yes');
		}
	}

	/**
	 * Copia o link do artigo para a área de transferência
	 */
	function handleCopyLink(e) {
		e.preventDefault();
		const url = window.location.href;

		// Usa a API moderna se disponível
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(url).then(() => {
				showCopyFeedback(e.target.closest('.Gstore-blog-single-share__button'));
			}).catch(() => {
				fallbackCopyText(url, e.target.closest('.Gstore-blog-single-share__button'));
			});
		} else {
			fallbackCopyText(url, e.target.closest('.Gstore-blog-single-share__button'));
		}
	}

	/**
	 * Método fallback para copiar texto (navegadores antigos)
	 */
	function fallbackCopyText(text, button) {
		const textArea = document.createElement('textarea');
		textArea.value = text;
		textArea.style.position = 'fixed';
		textArea.style.left = '-999999px';
		document.body.appendChild(textArea);
		textArea.focus();
		textArea.select();

		try {
			document.execCommand('copy');
			showCopyFeedback(button);
		} catch (err) {
			console.error('Erro ao copiar link:', err);
		} finally {
			document.body.removeChild(textArea);
		}
	}

	/**
	 * Mostra feedback visual ao copiar link
	 */
	function showCopyFeedback(button) {
		if (!button) {
			return;
		}

		const originalText = button.querySelector('span')?.textContent || '';
		const icon = button.querySelector('i');

		// Atualiza texto e ícone temporariamente
		if (button.querySelector('span')) {
			button.querySelector('span').textContent = 'Link copiado!';
		}
		if (icon) {
			icon.className = 'fa-solid fa-check';
		}

		button.style.background = 'var(--gstore-color-accent, #b5a642)';
		button.style.borderColor = 'var(--gstore-color-accent, #b5a642)';
		button.style.color = '#ffffff';

		// Restaura após 2 segundos
		setTimeout(() => {
			if (button.querySelector('span')) {
				button.querySelector('span').textContent = originalText;
			}
			if (icon) {
				icon.className = 'fa-solid fa-link';
			}
			button.style.background = '';
			button.style.borderColor = '';
			button.style.color = '';
		}, 2000);
	}

	/**
	 * Atualiza o breadcrumb com o título do post e URLs corretas
	 */
	function updateBreadcrumb() {
		const breadcrumb = document.querySelector('.Gstore-blog-single-breadcrumb');
		if (!breadcrumb) {
			return;
		}

		// Atualiza URL do blog (usa archive link se disponível)
		const blogLink = breadcrumb.querySelector('.Gstore-breadcrumb__blog');
		if (blogLink) {
			// Tenta encontrar a URL do archive de posts
			const archiveLink = document.querySelector('link[rel="canonical"]')?.href;
			if (archiveLink) {
				// Se houver um link canonical, pode indicar a estrutura
				const blogArchiveUrl = window.location.origin + '/blog';
				blogLink.href = blogArchiveUrl;
			}
		}

		// Atualiza título atual
		const breadcrumbCurrent = breadcrumb.querySelector('.Gstore-breadcrumb__current');
		if (breadcrumbCurrent) {
			const postTitle = document.querySelector('.Gstore-blog-single-title');
			if (postTitle) {
				breadcrumbCurrent.textContent = postTitle.textContent.trim();
			} else {
				// Fallback: pega do título da página
				const pageTitle = document.title.split('–')[0].split('|')[0].trim();
				breadcrumbCurrent.textContent = pageTitle;
			}
		}
	}

	/**
	 * Remove o post atual da lista de posts relacionados (se aparecer)
	 */
	function filterRelatedPosts() {
		const relatedQuery = document.querySelector('.Gstore-blog-single-related__query');
		if (!relatedQuery) {
			return;
		}

		// Obtém a URL atual
		const currentUrl = window.location.href;
		const currentPath = window.location.pathname;

		// Encontra todos os cards de posts relacionados
		const relatedCards = relatedQuery.querySelectorAll('.Gstore-blog-single-related__card');
		
		relatedCards.forEach(card => {
			const link = card.querySelector('a[href]');
			if (!link) {
				return;
			}

			const cardUrl = link.href;
			const cardPath = new URL(cardUrl).pathname;

			// Se o caminho for igual ao atual, remove o card
			if (cardPath === currentPath) {
				card.remove();
			}
		});
	}
})();

