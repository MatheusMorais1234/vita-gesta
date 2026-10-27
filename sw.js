// Service worker do app instalado: sempre busca a versão nova na rede.
// Sem internet, abre a última página guardada (o site público e a calculadora funcionam offline).
const CACHE = 'vitagesta-v1';
const ARQUIVOS = ['./', './index.html', './img/logo.png', './img/icon-192.png', './img/gestante.jpg', './img/gestante-poltrona.jpg'];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(ARQUIVOS)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then((ks) => Promise.all(ks.filter((k) => k !== CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim()));
});

self.addEventListener('fetch', (e) => {
  if (e.request.mode !== 'navigate') return; // API, fotos e fontes seguem direto para a rede
  e.respondWith(
    fetch(e.request)
      .then((r) => { const copia = r.clone(); caches.open(CACHE).then((c) => c.put('./index.html', copia)); return r; })
      .catch(() => caches.match('./index.html'))
  );
});
