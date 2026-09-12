/**
 * Edge redirect con Workers + Static Assets (módulo ES).
 * - Con sesión  -> sirve los assets (con SPA fallback para rutas del router).
 * - Sin sesión  -> redirige a /login?redirect=<ruta original>.
 * - Rutas públicas (/, /login, /register, /onboarding, /licencias/*) pasan siempre.
 */
export default {
  async fetch(request, env) {
    const url = new URL(request.url);
    const pathname = url.pathname;

    const isAsset = pathname.includes('.');
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

    return env.ASSETS.fetch(request);
  },
};