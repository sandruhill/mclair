import { useEffect, useRef, useState } from "react";

/**
 * Hero landing com fundo de vídeo HLS, header glassmórfico
 * e conteúdo do hero no canto inferior-esquerdo.
 *
 * COMO USAR / TROCAR PLACEHOLDERS:
 *  - HLS_SRC:    troque pela URL do seu stream .m3u8
 *  - POSTER:     imagem mostrada enquanto o vídeo carrega
 *  - NAV_LINKS:  itens do menu
 *  - HERO:       título, subtítulo e botão
 *
 * Requer hls.js. Este componente carrega via CDN automaticamente.
 * Se preferir instalar no projeto: `npm i hls.js` e
 * troque o carregamento por `import Hls from "hls.js"`.
 */

// ─── PLACEHOLDERS — edite aqui ──────────────────────────────
const HLS_SRC =
  "https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8"; // <- troque pela sua URL .m3u8
const POSTER = ""; // <- opcional: URL de uma imagem de capa
const BRAND = "mclair";
const NAV_LINKS = [
  { label: "Trabalhos", href: "#trabalhos" },
  { label: "Estúdio", href: "#estudio" },
  { label: "Contato", href: "#contato" },
];
const HERO = {
  eyebrow: "Estúdio criativo",
  title: "Imagens que ficam\nem movimento.",
  subtitle:
    "Direção, fotografia e pós-produção para marcas que não querem parecer todas iguais.",
  ctaLabel: "Ver o reel",
  ctaHref: "#trabalhos",
};
// ────────────────────────────────────────────────────────────

const HLS_CDN = "https://cdn.jsdelivr.net/npm/hls.js@1.5.13/dist/hls.min.js";

function loadHlsScript() {
  return new Promise((resolve, reject) => {
    if (window.Hls) return resolve(window.Hls);
    const existing = document.querySelector(`script[src="${HLS_CDN}"]`);
    if (existing) {
      existing.addEventListener("load", () => resolve(window.Hls));
      existing.addEventListener("error", reject);
      return;
    }
    const s = document.createElement("script");
    s.src = HLS_CDN;
    s.async = true;
    s.onload = () => resolve(window.Hls);
    s.onerror = reject;
    document.head.appendChild(s);
  });
}

export default function HeroLanding() {
  const videoRef = useRef(null);
  const [scrolled, setScrolled] = useState(false);
  const [menuOpen, setMenuOpen] = useState(false);

  // Header ganha fundo mais sólido após pequena rolagem
  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 24);
    window.addEventListener("scroll", onScroll, { passive: true });
    return () => window.removeEventListener("scroll", onScroll);
  }, []);

  // Setup do vídeo HLS
  useEffect(() => {
    const video = videoRef.current;
    if (!video) return;
    let hls;
    let cancelled = false;

    const nativeHls = video.canPlayType("application/vnd.apple.mpegurl");

    if (nativeHls) {
      // Safari / iOS reproduzem HLS nativamente
      video.src = HLS_SRC;
      video.play().catch(() => {});
    } else {
      loadHlsScript()
        .then((Hls) => {
          if (cancelled || !Hls || !Hls.isSupported()) return;
          hls = new Hls({ enableWorker: true, lowLatencyMode: false });
          hls.loadSource(HLS_SRC);
          hls.attachMedia(video);
          hls.on(Hls.Events.MANIFEST_PARSED, () => {
            video.play().catch(() => {});
          });
        })
        .catch(() => {
          // fallback silencioso: poster permanece visível
        });
    }

    return () => {
      cancelled = true;
      if (hls) hls.destroy();
    };
  }, []);

  return (
    <div className="ml-root">
      <style>{css}</style>

      {/* Header glassmórfico */}
      <header className={`ml-header ${scrolled ? "is-scrolled" : ""}`}>
        <a href="#top" className="ml-brand" aria-label={BRAND}>
          {BRAND}
          <span className="ml-brand-dot" />
        </a>

        <nav className={`ml-nav ${menuOpen ? "is-open" : ""}`}>
          {NAV_LINKS.map((link) => (
            <a key={link.href} href={link.href} className="ml-nav-link">
              {link.label}
            </a>
          ))}
          <a href={HERO.ctaHref} className="ml-nav-cta">
            Começar
          </a>
        </nav>

        <button
          className="ml-burger"
          aria-label="Abrir menu"
          aria-expanded={menuOpen}
          onClick={() => setMenuOpen((v) => !v)}
        >
          <span />
          <span />
        </button>
      </header>

      {/* Hero com vídeo de fundo */}
      <section className="ml-hero" id="top">
        <video
          ref={videoRef}
          className="ml-video"
          muted
          loop
          playsInline
          autoPlay
          poster={POSTER || undefined}
        />
        <div className="ml-scrim" aria-hidden="true" />

        <div className="ml-hero-content">
          {HERO.eyebrow && <span className="ml-eyebrow">{HERO.eyebrow}</span>}
          <h1 className="ml-title">
            {HERO.title.split("\n").map((line, i) => (
              <span key={i} className="ml-title-line">
                {line}
              </span>
            ))}
          </h1>
          <p className="ml-subtitle">{HERO.subtitle}</p>
          <a href={HERO.ctaHref} className="ml-cta">
            {HERO.ctaLabel}
            <svg
              width="18"
              height="18"
              viewBox="0 0 24 24"
              fill="none"
              aria-hidden="true"
            >
              <path
                d="M5 12h14M13 6l6 6-6 6"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
              />
            </svg>
          </a>
        </div>
      </section>
    </div>
  );
}

const css = `
  .ml-root {
    --ink: #f4f2ee;
    --bg: #0b0b0d;
    --accent: #c9f24d;
    --glass: rgba(18, 18, 20, 0.42);
    --glass-border: rgba(255, 255, 255, 0.14);
    font-family: "Inter", system-ui, -apple-system, sans-serif;
    color: var(--ink);
    background: var(--bg);
    min-height: 100vh;
  }
  .ml-root * { box-sizing: border-box; }

  /* Header */
  .ml-header {
    position: fixed;
    top: 0; left: 0; right: 0;
    z-index: 20;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 18px clamp(20px, 5vw, 64px);
    backdrop-filter: blur(14px) saturate(140%);
    -webkit-backdrop-filter: blur(14px) saturate(140%);
    background: linear-gradient(180deg, rgba(11,11,13,0.55), rgba(11,11,13,0));
    transition: background 0.3s ease, padding 0.3s ease, border-color 0.3s ease;
    border-bottom: 1px solid transparent;
  }
  .ml-header.is-scrolled {
    background: var(--glass);
    border-bottom: 1px solid var(--glass-border);
    padding-top: 14px;
    padding-bottom: 14px;
  }

  .ml-brand {
    font-weight: 600;
    font-size: 20px;
    letter-spacing: -0.03em;
    text-decoration: none;
    color: var(--ink);
    display: inline-flex;
    align-items: baseline;
    gap: 2px;
  }
  .ml-brand-dot {
    width: 6px; height: 6px;
    border-radius: 50%;
    background: var(--accent);
    align-self: flex-end;
    margin-bottom: 3px;
  }

  .ml-nav { display: flex; align-items: center; gap: clamp(20px, 3vw, 40px); }
  .ml-nav-link {
    color: var(--ink);
    text-decoration: none;
    font-size: 15px;
    opacity: 0.82;
    transition: opacity 0.2s ease;
  }
  .ml-nav-link:hover { opacity: 1; }
  .ml-nav-cta {
    color: var(--bg);
    background: var(--ink);
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    padding: 9px 18px;
    border-radius: 999px;
    transition: transform 0.2s ease, background 0.2s ease;
  }
  .ml-nav-cta:hover { transform: translateY(-1px); background: var(--accent); }

  .ml-burger {
    display: none;
    flex-direction: column;
    gap: 5px;
    background: none;
    border: none;
    cursor: pointer;
    padding: 8px;
  }
  .ml-burger span {
    width: 22px; height: 2px;
    background: var(--ink);
    border-radius: 2px;
  }

  /* Hero */
  .ml-hero {
    position: relative;
    width: 100%;
    height: 100vh;
    height: 100svh;
    overflow: hidden;
  }
  .ml-video {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    background: #111;
  }
  .ml-scrim {
    position: absolute;
    inset: 0;
    background:
      linear-gradient(90deg, rgba(0,0,0,0.65) 0%, rgba(0,0,0,0.15) 45%, rgba(0,0,0,0) 70%),
      linear-gradient(0deg, rgba(0,0,0,0.7) 0%, rgba(0,0,0,0) 40%);
  }

  .ml-hero-content {
    position: absolute;
    left: clamp(20px, 5vw, 64px);
    bottom: clamp(40px, 8vh, 88px);
    max-width: min(560px, 80vw);
    z-index: 5;
  }
  .ml-eyebrow {
    display: inline-block;
    font-size: 13px;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--accent);
    margin-bottom: 18px;
  }
  .ml-title {
    margin: 0 0 18px;
    font-size: clamp(38px, 6vw, 72px);
    line-height: 1.02;
    letter-spacing: -0.035em;
    font-weight: 600;
  }
  .ml-title-line { display: block; }
  .ml-subtitle {
    margin: 0 0 28px;
    font-size: clamp(15px, 1.4vw, 18px);
    line-height: 1.5;
    color: rgba(244, 242, 238, 0.82);
    max-width: 44ch;
  }
  .ml-cta {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    background: var(--accent);
    color: #0b0b0d;
    text-decoration: none;
    font-weight: 600;
    font-size: 16px;
    padding: 14px 26px;
    border-radius: 999px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
  }
  .ml-cta:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 30px rgba(201, 242, 77, 0.25);
  }
  .ml-cta svg { transition: transform 0.2s ease; }
  .ml-cta:hover svg { transform: translateX(3px); }

  /* Mobile */
  @media (max-width: 720px) {
    .ml-burger { display: flex; }
    .ml-nav {
      position: absolute;
      top: 100%; right: 16px;
      flex-direction: column;
      align-items: flex-start;
      gap: 14px;
      padding: 20px 22px;
      border-radius: 16px;
      background: var(--glass);
      border: 1px solid var(--glass-border);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      opacity: 0;
      pointer-events: none;
      transform: translateY(-8px);
      transition: opacity 0.2s ease, transform 0.2s ease;
    }
    .ml-nav.is-open { opacity: 1; pointer-events: auto; transform: translateY(8px); }
  }

  @media (prefers-reduced-motion: reduce) {
    .ml-cta, .ml-cta svg, .ml-nav-cta, .ml-nav { transition: none; }
  }
`;
