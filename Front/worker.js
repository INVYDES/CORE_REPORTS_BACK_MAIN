/**
 * Edge redirect con Workers + Static Assets (módulo ES).
 * - Con sesión  -> sirve los assets (con SPA fallback para rutas del router).
 * - Sin sesión  -> redirige a /?redirect=<ruta original>.
 * - Rutas públicas (/, /register, /onboarding, /licencias/*) pasan siempre.
 * - Assets con hash viejo (404) -> redirige a / para que el browser cargue el index.html fresco.
 */
export default {
  async fetch(request, env) {
    const url = new URL(request.url);
    const pathname = url.pathname;

    // Un asset es cualquier ruta que tenga extensión (.js, .css, .png, etc.)
    const isAsset = /\.\w+$/.test(pathname);

    // Rutas públicas que no requieren autenticación
    const isPublic = pathname === '/' ||
      pathname.startsWith('/register') ||
      pathname.startsWith('/onboarding') ||
      pathname.startsWith('/licencias/');

    // Protección de rutas privadas (no assets, no públicas)
    if (!isAsset && !isPublic) {
      const cookieHeader = request.headers.get('Cookie') || '';
      const hasJwt = /(?:^|;\s*)jwt=/.test(cookieHeader);
      if (!hasJwt) {
        const loginUrl = new URL('/', request.url);
        loginUrl.searchParams.set('redirect', pathname + url.search);
        return Response.redirect(loginUrl.toString(), 302);
      }
    }

    const res = await env.ASSETS.fetch(request);

    // ASSETS con "single-page-application" devuelve index.html para rutas inexistentes.
    // Si un asset con hash pide index.html, significa que el asset ya no existe en el
    // deploy actual (build viejo en caché del browser). Forzamos recarga a / para que
    // el browser obtenga el index.html fresco con los hashes correctos.
    if (isAsset && (res.headers.get('Content-Type') || '').includes('text/html')) {
      return new Response(
        `<!DOCTYPE html><html><head>
          <meta charset="utf-8">
          <title>Actualizando...</title>
          <script>
            // Asset obsoleto detectado: cargamos la app fresca
            sessionStorage.removeItem('token');
            window.location.replace('/');
          <\/script>
        </head><body>Actualizando aplicación...</body></html>`,
        {
          status: 200,
          headers: {
            'Content-Type': 'text/html;charset=UTF-8',
            'Cache-Control': 'no-store',
          },
        }
      );
    }

    // index.html y rutas del router: nunca cachear el documento HTML
    // para que el browser siempre referencie los chunks vigentes (los
    // hasheados sí se cachean inmutable por ASSETS automáticamente).
    if (!isAsset) {
      const headers = new Headers(res.headers);
      headers.set('Cache-Control', 'no-store, must-revalidate');
      headers.set('Pragma', 'no-cache');
      return new Response(res.body, { status: res.status, headers });
    }

    return res;
  },
};