addEventListener('fetch', event => {
  event.respondWith(handleRequest(event));
});

/**
 * Edge redirect: si la petición no apunta a un asset estático y no hay cookie
 * de sesión (jwt) en una ruta protegida, redirige a /login conservando el destino.
 * Las rutas públicas (/, /login, /register, /onboarding, /licencias/*) pasan.
 */
async function handleRequest(event) {
  const request = event.request;
  const url = new URL(request.url);
  const pathname = url.pathname;

  // Assets (con extensión) se sirven tal cual.
  const isAsset = pathname.includes('.');
  // Rutas públicas que no requieren sesión.
  const isPublic = pathname === '/' ||
    pathname.startsWith('/login') ||
    pathname.startsWith('/register') ||
    pathname.startsWith('/onboarding') ||
    pathname.startsWith('/licencias/');

  if (!isAsset && !isPublic) {
    const cookieHeader = request.headers.get('Cookie') || '';
    const hasJwt = /(?:^|;\s*)jwt=/.test(cookieHeader);
    if (!hasJwt) {
      const loginUrl = new URL('/login', request.url);
      loginUrl.searchParams.set('redirect', pathname + url.search);
      return Response.redirect(loginUrl.toString(), 302);
    }
  }
  // Para lo demás se sirve desde los assets del worker (SPA fallback incluido).
  return fetch(request);
}