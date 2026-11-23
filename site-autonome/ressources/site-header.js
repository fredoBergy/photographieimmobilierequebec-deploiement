(() => {
  document.querySelector('.site-unified-header')?.remove();
  const nested = location.pathname.includes('/realisations/');
  const base = nested ? '../' : '';
  const current = location.pathname.split('/').pop() || 'index.html';
  const servicePages = new Set(['services.html','drone.html','photographie-immobiliere.html','visite-360.html','montage-video.html','corporatif.html','publicite-radio.html']);
  const header = document.createElement('header');
  header.className = 'site-unified-header';
  header.innerHTML = `
    <a class="site-unified-brand" href="${base}index.html">Photographie immobilière Québec</a>
    <button class="site-menu-toggle" type="button" aria-expanded="false" aria-label="Ouvrir le menu principal"><span></span><span></span><span></span></button>
    <nav class="site-unified-nav" aria-label="Navigation principale">
      <a href="${base}index.html">Accueil</a>
      <a href="${base}a-propos.html">À propos</a>
      <div class="site-services">
        <a class="site-services-link" href="${base}services.html">Services</a>
        <button class="site-services-toggle" type="button" aria-expanded="false" aria-label="Afficher les services">⌄</button>
        <div class="site-services-submenu">
          <a href="${base}photographie-immobiliere.html">Photographie immobilière</a>
          <a href="${base}drone.html">Photo et vidéo par drone</a>
          <a href="${base}visite-360.html">Visite virtuelle 360</a>
          <a href="${base}montage-video.html">Montage vidéo</a>
          <a href="${base}publicite-radio.html">Publicité radio</a>
        </div>
      </div>
      <a href="${base}dernieres-realisations.html">Réalisations</a>
      <a href="${base}tarifs.html">Tarifs</a>
      <a href="${base}contact.html">Contact</a>
    </nav>`;
  document.body.prepend(header);

  header.querySelectorAll('a').forEach(link => {
    if (link.getAttribute('href').split('/').pop() === current) link.setAttribute('aria-current', 'page');
  });
  if (servicePages.has(current)) header.querySelector('.site-services-link').setAttribute('aria-current', 'page');

  const menuButton = header.querySelector('.site-menu-toggle');
  const services = header.querySelector('.site-services');
  const servicesButton = header.querySelector('.site-services-toggle');
  const closeMenu = () => {
    header.classList.remove('site-menu-open');
    menuButton.setAttribute('aria-expanded', 'false');
    menuButton.setAttribute('aria-label', 'Ouvrir le menu principal');
  };
  menuButton.addEventListener('click', () => {
    const open = header.classList.toggle('site-menu-open');
    menuButton.setAttribute('aria-expanded', String(open));
    menuButton.setAttribute('aria-label', `${open ? 'Fermer' : 'Ouvrir'} le menu principal`);
  });
  servicesButton.addEventListener('click', () => {
    const open = services.classList.toggle('site-submenu-open');
    servicesButton.setAttribute('aria-expanded', String(open));
  });
  header.querySelectorAll('nav a').forEach(link => link.addEventListener('click', closeMenu));
  document.addEventListener('keydown', event => { if (event.key === 'Escape') { closeMenu(); services.classList.remove('site-submenu-open'); } });
})();
