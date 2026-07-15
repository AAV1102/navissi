export default {
  async fetch(request) {
    const originUrl = new URL(request.url);
    originUrl.protocol = 'http:';
    const originRequest = new Request(originUrl, request);
    const headers = new Headers(originRequest.headers);
    headers.set('X-Navissi-Original-Proto', 'https');
    return fetch(new Request(originRequest, { headers }));
  },

  async scheduled(_controller, env, ctx) {
    ctx.waitUntil(fetch('https://grupo10z.com.co/api_correo_mesadeayuda.php', {
      headers: { 'X-Navissi-Correo-Token': env.CRON_TOKEN },
    }));
  },
};
