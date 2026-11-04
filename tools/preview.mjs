#!/usr/bin/env node
/**
 * Gera preview.html: o README.md renderizado com o CSS de markdown do GitHub.
 * É uma aproximação local para conferir o visual antes de dar push.
 *
 * Uso: node tools/preview.mjs   -> abra preview.html no navegador
 *      (preview.html#dark abre já no tema escuro)
 */
import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const md = readFileSync(join(root, 'README.md'), 'utf8').replace(/<\/script/gi, '<\\/script');

const html = `<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Prévia do README</title>
<link id="md-css" rel="stylesheet" href="https://cdn.jsdelivr.net/npm/github-markdown-css@5.5.1/github-markdown-light.css">
<style>
  body { margin: 0; background: #ffffff; font-family: -apple-system, 'Segoe UI', sans-serif; }
  body.dark { background: #0d1117; }
  .bar, .box { max-width: 940px; margin: 0 auto; padding: 0 16px; box-sizing: border-box; }
  .bar { display: flex; justify-content: flex-end; padding-top: 16px; }
  .bar button { font: 13px/1 inherit; padding: 7px 12px; border-radius: 6px; border: 1px solid #d0d7de; background: #f6f8fa; cursor: pointer; }
  .box { padding-top: 12px; padding-bottom: 40px; }
  .markdown-body { border: 1px solid #d0d7de; border-radius: 6px; padding: 24px; }
  body.dark .markdown-body { border-color: #30363d; }
</style>
</head>
<body>
<div class="bar"><button id="theme" type="button">Alternar claro/escuro</button></div>
<div class="box"><article class="markdown-body" id="out"></article></div>
<script id="md" type="text/plain">${md}</script>
<script src="https://cdn.jsdelivr.net/npm/marked@12.0.2/marked.min.js"></script>
<script>
  document.getElementById('out').innerHTML = marked.parse(document.getElementById('md').textContent);
  const css = document.getElementById('md-css');
  document.getElementById('theme').onclick = () => {
    const dark = document.body.classList.toggle('dark');
    css.href = css.href.replace(/github-markdown-(light|dark)/, dark ? 'github-markdown-dark' : 'github-markdown-light');
  };
  if (location.hash === '#dark') document.getElementById('theme').click();
</script>
</body>
</html>
`;

writeFileSync(join(root, 'preview.html'), html, 'utf8');
console.log('preview.html gerado');
