/**
 * Links de vídeo -> player embutido.
 *
 * Um link de vídeo sozinho numa linha do post vira o player; um link no meio de
 * uma frase continua sendo link, porque ali ele é referência, não mídia.
 *
 * Só entram plataformas que aceitam iframe direto, sem script de terceiro: isso
 * mantém a Content-Security-Policy do site curta (basta liberar o domínio em
 * frame-src) e evita carregar rastreador de rede social em toda página.
 */

export type VideoEmbed = {
  src: string;
  /** Vertical (Shorts, Reels, TikTok) ocupa menos largura e usa 9:16. */
  vertical: boolean;
  titulo: string;
};

/** Domínios que precisam estar em frame-src para os players funcionarem. */
export const DOMINIOS_DE_VIDEO = [
  'https://www.youtube.com',
  'https://www.tiktok.com',
  'https://www.instagram.com',
  'https://player.vimeo.com',
];

function idDoYoutube(u: URL): string | null {
  const host = u.hostname.replace(/^www\./, '');
  if (host === 'youtu.be') return u.pathname.slice(1).split('/')[0] || null;
  if (host !== 'youtube.com' && host !== 'm.youtube.com') return null;
  if (u.pathname === '/watch') return u.searchParams.get('v');
  const m = u.pathname.match(/^\/(?:shorts|embed|live|v)\/([^/?#]+)/);
  return m ? m[1] : null;
}

/**
 * Reconhece a URL. Devolve null quando não é vídeo de plataforma conhecida —
 * aí o link fica como link, que é o comportamento correto.
 */
export function lerVideo(url: string): VideoEmbed | null {
  let u: URL;
  try {
    u = new URL(url.trim());
  } catch {
    return null;
  }
  if (u.protocol !== 'https:' && u.protocol !== 'http:') return null;

  const host = u.hostname.replace(/^www\./, '');

  // YouTube, incluindo Shorts
  const yt = idDoYoutube(u);
  if (yt) {
    const ehShort = /\/shorts\//.test(u.pathname);
    return {
      src: `https://www.youtube-nocookie.com/embed/${encodeURIComponent(yt)}`,
      vertical: ehShort,
      titulo: 'Vídeo do YouTube',
    };
  }

  // TikTok: só o formato com id numérico. O link curto (vm.tiktok.com) exige
  // resolver um redirecionamento em tempo de build, então fica como link.
  if (host === 'tiktok.com') {
    const m = u.pathname.match(/\/video\/(\d+)/);
    if (m) {
      return {
        src: `https://www.tiktok.com/embed/v2/${m[1]}`,
        vertical: true,
        titulo: 'Vídeo do TikTok',
      };
    }
    return null;
  }

  // Instagram: Reels e posts
  if (host === 'instagram.com') {
    const m = u.pathname.match(/^\/(?:reel|reels|p|tv)\/([^/?#]+)/);
    if (m) {
      return {
        src: `https://www.instagram.com/${u.pathname.split('/')[1]}/${m[1]}/embed/`,
        vertical: true,
        titulo: 'Vídeo do Instagram',
      };
    }
    return null;
  }

  // Vimeo
  if (host === 'vimeo.com') {
    const m = u.pathname.match(/^\/(\d+)/);
    if (m) {
      return {
        src: `https://player.vimeo.com/video/${m[1]}`,
        vertical: false,
        titulo: 'Vídeo do Vimeo',
      };
    }
    return null;
  }

  return null;
}

export function htmlDoVideo(v: VideoEmbed): string {
  const classe = v.vertical ? 'video-embed video-embed-vertical' : 'video-embed';
  return (
    `<div class="${classe}">` +
    `<iframe src="${v.src}" title="${v.titulo}" loading="lazy" allowfullscreen ` +
    `allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" ` +
    `referrerpolicy="strict-origin-when-cross-origin"></iframe>` +
    `</div>`
  );
}

/**
 * Roda sobre o HTML já gerado pelo marked. Procura parágrafo cujo conteúdo
 * inteiro é um único link — é assim que um link colado numa linha sozinha sai
 * do marked — e troca pelo player.
 */
export function embutirVideos(html: string): string {
  return html.replace(
    /<p>\s*<a\s+href="([^"]+)"[^>]*>([^<]*)<\/a>\s*<\/p>/g,
    (inteiro, href: string, texto: string) => {
      // Se o texto do link foi escrito pelo autor ("veja o vídeo"), ele quis um
      // link, não um player. Só converte quando o texto é a própria URL.
      const textoLimpo = texto.trim();
      const hrefLimpo = href.trim();
      const ehUrlNua =
        textoLimpo === hrefLimpo ||
        textoLimpo === hrefLimpo.replace(/^https?:\/\//, '') ||
        textoLimpo === decodeURI(hrefLimpo);
      if (!ehUrlNua) return inteiro;

      const v = lerVideo(hrefLimpo);
      return v ? htmlDoVideo(v) : inteiro;
    }
  );
}

/**
 * Capa em vídeo do post. Aceita link de plataforma (vira player) ou arquivo
 * .mp4/.webm hospedado (vira <video> nativo, sem depender de terceiro).
 */
export function htmlDaCapaEmVideo(url: string): string | null {
  const limpo = (url || '').trim();
  if (!limpo) return null;

  const v = lerVideo(limpo);
  if (v) return htmlDoVideo(v);

  if (/\.(mp4|webm|ogv)(\?|#|$)/i.test(limpo)) {
    const src = limpo.replace(/"/g, '%22');
    return (
      '<div class="video-embed video-embed-arquivo">' +
      `<video src="${src}" controls preload="metadata" playsinline></video>` +
      '</div>'
    );
  }
  return null;
}
