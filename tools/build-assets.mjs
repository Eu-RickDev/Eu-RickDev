#!/usr/bin/env node
/**
 * Gera os SVGs do README do perfil no estilo sumi-e do RickDev
 * (papel washi, nanquim, ouro e carimbo hanko).
 *
 * Uso: node tools/build-assets.mjs   -> escreve tudo em ./assets
 *
 * Só usa fontes do sistema: o GitHub mostra estes arquivos dentro de <img>,
 * e SVG dentro de <img> não carrega fonte externa.
 */
import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const OUT = join(dirname(fileURLToPath(import.meta.url)), '..', 'assets');
mkdirSync(OUT, { recursive: true });

// Paleta oficial (a mesma do portfólio e do NUI). Não inventar cores novas.
const C = {
  paper: '#f3ebdd', paper2: '#ece0c9', ink: '#1b1815', inkSoft: '#4a423a',
  gold: '#d9a441', goldDeep: '#b8842c', seal: '#8f3b34', sakura: '#c98a8a',
};
const SERIF = "Georgia, 'Times New Roman', serif";
const SANS = "'Segoe UI', 'Helvetica Neue', Arial, sans-serif";
const JP = "'Yu Mincho', 'Hiragino Mincho ProN', 'Noto Serif JP', 'Noto Serif CJK JP', 'MS Mincho', serif";

const f = (n) => +n.toFixed(1);
const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

function write(name, content) {
  writeFileSync(join(OUT, name), content.trim() + '\n', 'utf8');
  console.log('  assets/' + name);
}

// ---------------------------------------------------------------- peças comuns

const FILTERS = `
  <filter id="rough" x="-10%" y="-30%" width="120%" height="160%">
    <feTurbulence type="fractalNoise" baseFrequency="0.035" numOctaves="2" seed="4" result="n"/>
    <feDisplacementMap in="SourceGraphic" in2="n" scale="5" xChannelSelector="R" yChannelSelector="G"/>
  </filter>
  <filter id="roughS" x="-5%" y="-30%" width="110%" height="160%">
    <feTurbulence type="fractalNoise" baseFrequency="0.06" numOctaves="1" seed="9" result="n"/>
    <feDisplacementMap in="SourceGraphic" in2="n" scale="1.6" xChannelSelector="R" yChannelSelector="G"/>
  </filter>
  <filter id="roughT" x="-5%" y="-10%" width="110%" height="120%">
    <feTurbulence type="fractalNoise" baseFrequency="0.05" numOctaves="2" seed="21" result="n"/>
    <feDisplacementMap in="SourceGraphic" in2="n" scale="2.6" xChannelSelector="R" yChannelSelector="G"/>
  </filter>
  <filter id="brushF" x="-3%" y="-120%" width="106%" height="340%">
    <feTurbulence type="fractalNoise" baseFrequency="0.03 0.12" numOctaves="2" seed="5" result="n"/>
    <feDisplacementMap in="SourceGraphic" in2="n" scale="5" xChannelSelector="R" yChannelSelector="G"/>
  </filter>
  <filter id="grain" x="0" y="0" width="100%" height="100%">
    <feTurbulence type="fractalNoise" baseFrequency="0.85" numOctaves="2" seed="11" stitchTiles="stitch"/>
    <feColorMatrix values="0 0 0 0 0.106  0 0 0 0 0.094  0 0 0 0 0.082  1.4 0 0 0 -0.62"/>
  </filter>
  <filter id="fibers" x="0" y="0" width="100%" height="100%">
    <feTurbulence type="fractalNoise" baseFrequency="0.005 0.08" numOctaves="2" seed="2" stitchTiles="stitch"/>
    <feColorMatrix values="0 0 0 0 0.72  0 0 0 0 0.52  0 0 0 0 0.17  1.6 0 0 0 -0.85"/>
  </filter>
  <radialGradient id="vignette" cx="50%" cy="45%" r="75%">
    <stop offset="55%" stop-color="${C.paper}" stop-opacity="0"/>
    <stop offset="100%" stop-color="${C.paper2}"/>
  </radialGradient>`;

/** Folha de papel com textura; `body` vai entre o papel e o granulado. */
function svg(w, h, body, { defs = '', css = '', label = '', rx = 14 } = {}) {
  return `<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${w} ${h}" width="${w}" height="${h}" role="img" aria-label="${esc(label)}">
<title>${esc(label)}</title>
<defs>${FILTERS}
  <clipPath id="frame"><rect width="${w}" height="${h}" rx="${rx}"/></clipPath>${defs}
</defs>${css ? `\n<style>${css}</style>` : ''}
<g clip-path="url(#frame)">
  <rect width="${w}" height="${h}" fill="${C.paper}"/>
  <rect width="${w}" height="${h}" filter="url(#fibers)" opacity=".45"/>
  <rect width="${w}" height="${h}" fill="url(#vignette)"/>
  ${body.trim()}
  <rect width="${w}" height="${h}" filter="url(#grain)" opacity=".55"/>
</g>
</svg>`;
}

/** Pincelada horizontal que afina nas pontas, com rastro de pincel seco no fim. */
function brush(x, y, len, h, color = C.gold, opacity = 0.92) {
  const p = (a, b) => `${f(x + a * len)},${f(y + b * h)}`;
  const d = `M${p(0, 0.15)} C${p(0.04, -0.5)} ${p(0.22, -0.62)} ${p(0.48, -0.52)} `
    + `C${p(0.74, -0.44)} ${p(0.92, -0.34)} ${p(1, 0)} `
    + `C${p(0.9, 0.18)} ${p(0.72, 0.4)} ${p(0.46, 0.48)} `
    + `C${p(0.24, 0.55)} ${p(0.05, 0.62)} ${p(0, 0.15)} Z`;
  const streaks = [-0.22, 0.05, 0.28].map((o, i) =>
    `<path d="M${p(0.7 + i * 0.04, o)} L${p(1.02 - i * 0.02, o * 0.6 - 0.02)}" stroke="${color}" `
    + `stroke-width="${f(Math.max(1, h * 0.09))}" stroke-linecap="round" opacity=".55"/>`).join('');
  return `<g opacity="${opacity}" filter="url(#brushF)"><path d="${d}" fill="${color}"/>${streaks}</g>`;
}

/** Carimbo hanko com リック na vertical. */
function hanko(x, y, rot = -5, size = 66, cls = '') {
  const s = size / 2;
  const i = s - 6;
  const fs = f(size * 0.23);
  const step = f(size * 0.235);
  const chars = ['リ', 'ッ', 'ク'].map((ch, k) =>
    `<text x="0" y="${f((k - 1) * step + fs * 0.36)}" text-anchor="middle" font-family="${JP}" `
    + `font-size="${fs}" font-weight="700" fill="${C.paper}">${ch}</text>`).join('');
  return `<g transform="translate(${x} ${y}) rotate(${rot})"><g class="${cls}">
    <rect x="${-s}" y="${-s}" width="${size}" height="${size}" rx="${f(size * 0.1)}" fill="${C.seal}" filter="url(#rough)"/>
    <rect x="${-i}" y="${-i}" width="${i * 2}" height="${i * 2}" rx="4" fill="none" stroke="${C.paper}" stroke-width="1.6" opacity=".85"/>
    ${chars}
  </g></g>`;
}

/** Katana deitada, cabo à esquerda (x 0) e ponta à direita (x ~650). */
function katana() {
  const diamonds = Array.from({ length: 9 }, (_, i) => {
    const cx = 20 + i * 14.5;
    return `<path d="M${cx - 5},0 L${cx},-7 L${cx + 5},0 L${cx},7 Z" fill="${C.paper2}" opacity=".85"/>`;
  }).join('');

  // hamon: linha ondulada da têmpera, acompanhando o fio
  const pts = [];
  for (let i = 0; i <= 64; i++) {
    const t = i / 64;
    const x = (1 - t) ** 2 * 184 + 2 * (1 - t) * t * 420 + t * t * 620;
    const y = (1 - t) ** 2 * 3 + 2 * (1 - t) * t * -2 + t * t * -19;
    const wave = Math.sin(t * Math.PI * 22) * 1.3 * (1 - t * 0.5);
    pts.push(`${f(x)},${f(y + wave)}`);
  }

  return `
    <g filter="url(#roughS)">
      <path d="M180,-7 Q420,-12 626,-30 L652,-35 Q645,-24 628,-17 Q420,2 180,7 Z" fill="url(#steel)" stroke="${C.ink}" stroke-width="1.5" stroke-linejoin="round"/>
      <path d="M182,-2.5 Q420,-7.5 624,-25.5" fill="none" stroke="#7a6f64" stroke-width="1" opacity=".8"/>
      <polyline points="${pts.join(' ')}" fill="none" stroke="${C.paper}" stroke-width="1.6" opacity=".55" stroke-linecap="round"/>
      <path d="M626,-30 L628,-17" stroke="${C.paper}" stroke-width="1" opacity=".45"/>
      <rect x="163" y="-8.5" width="18" height="17" rx="2" fill="${C.gold}"/>
      <path d="M168,-8.5 L176,8.5" stroke="${C.goldDeep}" stroke-width="1.2"/>
      <rect x="160" y="-16" width="3.5" height="32" rx="1" fill="${C.goldDeep}"/>
      <rect x="149" y="-31" width="11" height="62" rx="5" fill="${C.ink}" stroke="${C.goldDeep}" stroke-width="1.6"/>
      <rect x="145.5" y="-16" width="3.5" height="32" rx="1" fill="${C.goldDeep}"/>
      <rect x="6" y="-11" width="140" height="22" rx="6" fill="${C.ink}"/>
      ${diamonds}
      <ellipse cx="78" cy="0" rx="10" ry="3.2" fill="${C.gold}"/>
      <rect x="-5" y="-12.5" width="14" height="25" rx="5" fill="${C.goldDeep}"/>
    </g>`;
}

const KATANA_DEFS = `
  <linearGradient id="steel" x1="0" y1="-12" x2="0" y2="8" gradientUnits="userSpaceOnUse">
    <stop offset="0" stop-color="#6b6158"/>
    <stop offset=".45" stop-color="#3a332d"/>
    <stop offset="1" stop-color="${C.ink}"/>
  </linearGradient>
  <g id="katana">${katana()}</g>`;

const PETAL = `<path d="M0,7 C-5,3 -6,-3 -2.2,-7 L0,-4.6 L2.2,-7 C6,-3 5,3 0,7 Z" fill="${C.sakura}"/>`;

// ---------------------------------------------------------------- banner

{
  // x, duração (s), atraso (s), y de repouso quando não há animação
  const petals = [
    [110, 9, -1, 120], [250, 12, -6, 300], [420, 10, -3.5, 60], [560, 14, -11, 384],
    [720, 13, -8, 40], [880, 11, -2, 330], [1010, 9.5, -5.5, 110], [1130, 12.5, -9, 260],
  ].map(([x, dur, delay, y], i) =>
    `<g transform="translate(${x} 0)"><g class="petal ${i % 2 ? 'b' : 'a'}" transform="translate(0 ${y}) rotate(${i * 40})" `
    + `style="animation-duration:${dur}s;animation-delay:${delay}s" opacity="${[0.55, 0.7, 0.85][i % 3]}">${PETAL}</g></g>`,
  ).join('\n  ');

  const css = `
  .k { transform-box: view-box; transform-origin: 0 0; animation: 2.3s cubic-bezier(.75,0,.2,1) both; }
  .kA { animation-name: kA; }
  .kB { animation-name: kB; }
  @keyframes kA { 0%, 28% { transform: translate(600px, 212px) rotate(-17deg); } 100% { transform: translate(600px, 84px) rotate(-3deg); } }
  @keyframes kB { 0%, 28% { transform: translate(600px, 212px) rotate(17deg); } 100% { transform: translate(600px, 346px) rotate(-3deg); } }
  .enso { animation: enso 1.7s cubic-bezier(.6,.05,.3,1) .1s both; }
  .enso.e2 { animation-delay: .25s; }
  @keyframes enso { from { stroke-dashoffset: 1081; } }
  .reveal { animation: reveal 1s ease-out 1.25s both; }
  .reveal.sub { animation-delay: 1.55s; }
  @keyframes reveal { from { opacity: 0; transform: translateY(10px); } }
  .swipe { transform-box: fill-box; transform-origin: 0% 50%; animation: swipe .75s cubic-bezier(.6,0,.2,1) 1.6s both; }
  @keyframes swipe { from { transform: scaleX(0); } }
  .stamp { transform-box: fill-box; transform-origin: 50% 50%; animation: stamp .5s cubic-bezier(.3,1.5,.5,1) 2.2s both; }
  @keyframes stamp { from { transform: scale(1.7); opacity: 0; } }
  .petal { transform-box: view-box; transform-origin: 0 0; animation-timing-function: ease-in-out; animation-iteration-count: infinite; }
  .petal.a { animation-name: fallA; }
  .petal.b { animation-name: fallB; }
  @keyframes fallA { 0% { transform: translate(0px, -20px) rotate(0deg); } 50% { transform: translate(45px, 200px) rotate(170deg); } 100% { transform: translate(10px, 445px) rotate(340deg); } }
  @keyframes fallB { 0% { transform: translate(0px, -20px) rotate(0deg); } 50% { transform: translate(-40px, 210px) rotate(-150deg); } 100% { transform: translate(-5px, 445px) rotate(-320deg); } }
  @media (prefers-reduced-motion: reduce) { .k, .enso, .reveal, .swipe, .stamp, .petal { animation: none; } }`;

  const vertical = ['墨', 'と', '刀'].map((ch, i) =>
    `<text x="78" y="${170 + i * 27}" text-anchor="middle" font-family="${JP}" font-size="21" fill="${C.inkSoft}" opacity=".75">${ch}</text>`).join('');

  write('banner.svg', svg(1200, 420, `
  <rect x="14" y="14" width="1172" height="392" rx="9" fill="none" stroke="${C.ink}" stroke-opacity=".16" stroke-width="1.3" filter="url(#rough)"/>
  <g opacity=".13" filter="url(#rough)">
    <circle class="enso" cx="600" cy="212" r="172" fill="none" stroke="${C.ink}" stroke-width="30" stroke-linecap="round" stroke-dasharray="1081" stroke-dashoffset="110" transform="rotate(-62 600 212)"/>
    <circle class="enso e2" cx="606" cy="207" r="168" fill="none" stroke="${C.ink}" stroke-width="10" stroke-linecap="round" stroke-dasharray="1056" stroke-dashoffset="150" transform="rotate(-55 600 212)"/>
  </g>
  <g fill="${C.ink}" opacity=".5" filter="url(#rough)">
    <circle cx="1080" cy="96" r="7"/><circle cx="1099" cy="84" r="2.6"/><circle cx="1063" cy="113" r="1.8"/>
    <circle cx="142" cy="342" r="5"/><circle cx="158" cy="356" r="2"/>
  </g>
  ${vertical}
  <circle cx="78" cy="248" r="3.5" fill="${C.seal}"/>
  <g class="k kA" transform="translate(600 84) rotate(-3)"><use href="#katana" transform="translate(-326 0)"/></g>
  <g class="k kB" transform="translate(600 346) rotate(-3)"><use href="#katana" transform="scale(-1 1) translate(-326 0)"/></g>
  <g class="swipe">${brush(352, 207, 500, 46)}</g>
  <text class="reveal" x="600" y="238" text-anchor="middle" font-family="${SERIF}" font-size="118" font-weight="700" letter-spacing="3" fill="${C.ink}" filter="url(#roughT)">RickDev</text>
  <text class="reveal sub" x="600" y="286" text-anchor="middle" font-family="${SERIF}" font-size="17" letter-spacing="7" fill="${C.inkSoft}">FIVEM · QBCORE · NUI · DESKTOP · ROBLOX</text>
  ${hanko(905, 190, -5, 66, 'stamp')}
  ${petals}
`, { defs: KATANA_DEFS, css, label: 'RickDev: duas katanas se separam e revelam o nome' }));
}

// ---------------------------------------------------------------- títulos de seção

function header(file, kanji, title) {
  const kw = kanji.length * 46;
  const sep = 44 + kw + 18;
  const tx = sep + 22;
  const tw = title.length * 26 * 0.74 + (title.length - 1) * 8;
  const ls = tx + tw + 34;
  write(file, svg(1200, 96, `
  <text x="44" y="66" font-family="${JP}" font-size="46" font-weight="700" fill="${C.seal}" filter="url(#roughS)">${kanji}</text>
  <line x1="${sep}" y1="30" x2="${sep}" y2="68" stroke="${C.ink}" stroke-opacity=".3" stroke-width="1.4"/>
  <text x="${tx}" y="59" font-family="${SERIF}" font-size="26" font-weight="700" letter-spacing="8" fill="${C.ink}">${esc(title)}</text>
  ${brush(ls, 50, 1132 - ls, 8, C.gold, 0.85)}
  <circle cx="1152" cy="50" r="4.5" fill="${C.seal}"/>
`, { label: `${kanji} ${title}`, rx: 12 }));
}

header('h-sobre.svg', '道', 'SOBRE');
header('h-arsenal.svg', '武器', 'ARSENAL');
header('h-projetos.svg', '作品', 'PROJETOS');
header('h-bancada.svg', '工房', 'NA BANCADA');
header('h-contato.svg', '連絡', 'CONTATO');

// ---------------------------------------------------------------- cards de projeto

/** Canto inferior direito: para onde o card leva (site, repo) ou cadeado se o código é privado. */
function cardLink(link) {
  const label = (text) => `<text x="${{ site: 532, repo: 522 }[link] ?? 524}" y="246" text-anchor="end" font-family="${SANS}" font-size="12" font-weight="700" letter-spacing="2.5" fill="${C.inkSoft}">${text}</text>`;
  if (link === 'site') return `${label('SITE')}<text x="560" y="250" text-anchor="end" font-family="${SERIF}" font-size="26" fill="${C.goldDeep}">↗</text>`;
  if (link === 'repo') return `${label('REPO')}<text x="560" y="250" text-anchor="end" font-family="${SERIF}" font-size="28" fill="${C.goldDeep}">→</text>`;
  return `${label('PRIVADO')}
  <g transform="translate(546 242)" fill="${C.inkSoft}">
    <path d="M-6,-3 V-8 a6,6 0 0 1 12,0 V-3" fill="none" stroke="${C.inkSoft}" stroke-width="2.4"/>
    <rect x="-9" y="-3" width="18" height="14" rx="3"/>
    <circle cx="0" cy="3.5" r="2" fill="${C.paper}"/>
  </g>`;
}

function card(file, { kanji, title, status, lines, tags, link = null }) {
  const tw = title.length * 38 * 0.56;
  let x = 40;
  const pills = tags.map((t) => {
    const w = f(t.length * 7.9 + 28);
    const g = `<rect x="${f(x)}" y="226" width="${w}" height="30" rx="15" fill="${C.paper2}" stroke="${C.goldDeep}" stroke-width="1.4"/>`
      + `<text x="${f(x + w / 2)}" y="246" text-anchor="middle" font-family="${SANS}" font-size="14" font-weight="600" fill="${C.ink}">${esc(t)}</text>`;
    x += w + 10;
    return g;
  }).join('\n  ');

  write(file, svg(600, 300, `
  <text x="574" y="282" text-anchor="end" font-family="${JP}" font-size="250" fill="${C.ink}" opacity=".06">${kanji}</text>
  <rect x="10" y="10" width="580" height="280" rx="10" fill="none" stroke="${C.ink}" stroke-opacity=".2" stroke-width="1.3" filter="url(#rough)"/>
  <rect x="40" y="42" width="12" height="12" rx="2" fill="${C.seal}" transform="rotate(-6 46 48)"/>
  <text x="62" y="53" font-family="${SANS}" font-size="13" font-weight="700" letter-spacing="3" fill="${C.inkSoft}">${esc(status)}</text>
  ${brush(36, 104, Math.min(tw * 0.92, 470), 16, C.gold, 0.55)}
  <text x="40" y="112" font-family="${SERIF}" font-size="38" font-weight="700" fill="${C.ink}">${esc(title)}</text>
  ${lines.map((l, i) => `<text x="40" y="${168 + i * 27}" font-family="${SANS}" font-size="18" fill="${C.inkSoft}">${esc(l)}</text>`).join('\n  ')}
  ${pills}
  ${cardLink(link)}
`, { label: `${title}: ${lines.join(' ')}` }));
}

card('card-grimoire.svg', {
  kanji: '書', title: 'Grimoire.pro', status: 'DESKTOP · FASE 1',
  lines: ['Companion de Deadlock: counter-build reativo aos', 'itens do inimigo, pacing de souls e overlay in-game.'],
  tags: ['Tauri 2', 'Rust', 'JavaScript', 'SQLite'], link: 'site',
});
card('card-speedcam.svg', {
  kanji: '速', title: 'RickDev Speedcam', status: 'FIVEM · v1.1.0',
  lines: ['Câmera com sensação de velocidade: FOV, zoom out,', 'shake e blur crescendo com o km/h, e modo drift.'],
  tags: ['Lua', 'NUI', 'Scripted Cam'],
});
card('card-steeringwheel.svg', {
  kanji: '輪', title: 'Steering Wheel', status: 'FIVEM · v1.0.0',
  lines: ['Compatibilidade de volante no FiveM sem instalar nada.', 'Gamepad API, câmera e Force Feedback via WebHID.'],
  tags: ['Lua', 'NUI', 'WebHID', 'WebRTC'],
});
card('card-fishnchips.svg', {
  kanji: '魚', title: "Fish N' Chips", status: 'OUTAGE GAMES · EM DESENVOLVIMENTO',
  lines: ['Jogo de pesca com tycoon de restaurante no Roblox,', 'feito pela Outage Games, o meu estúdio.'],
  tags: ['Luau', 'Rojo', 'Roblox Studio'],
});
card('card-presence.svg', {
  kanji: '絆', title: 'Presence Studio', status: 'DESKTOP · v1.1.0',
  lines: ['Monte e controle sua Rich Presence do Discord', 'numa interface visual, sem mexer em código.'],
  tags: ['Electron', 'JavaScript', 'Discord RPC'], link: 'repo',
});
card('card-mine.svg', {
  kanji: '鉱', title: 'Project Mine', status: 'ROBLOX · EM DESENVOLVIMENTO',
  lines: ['Simulator de mineração em ilhas flutuantes, com', 'núcleos, chuva de cristais e drones colecionáveis.'],
  tags: ['Luau', 'Rojo', 'Roblox Studio'],
});

// ---------------------------------------------------------------- rodapé

write('footer.svg', svg(1200, 170, `
  <g transform="translate(150 92) rotate(-8) scale(.36)"><use href="#katana" transform="translate(-326 0)"/></g>
  <text x="600" y="80" text-anchor="middle" font-family="${SERIF}" font-style="italic" font-size="32" fill="${C.ink}">Valeu pela visita.</text>
  <text x="600" y="114" text-anchor="middle" font-family="${SANS}" font-size="14" letter-spacing="5" fill="${C.inkSoft}">ありがとう · FEITO COM NANQUIM, LUA E CAFÉ</text>
  ${brush(500, 138, 200, 7, C.gold, 0.85)}
  ${hanko(1060, 85, 4, 58)}
  <g transform="translate(985 38) rotate(30)" opacity=".7">${PETAL}</g>
  <g transform="translate(250 140) rotate(-50)" opacity=".6">${PETAL}</g>
  <g transform="translate(905 142) rotate(80)" opacity=".5">${PETAL}</g>
`, { defs: KATANA_DEFS, label: 'Valeu pela visita', rx: 12 }));

console.log('pronto.');
