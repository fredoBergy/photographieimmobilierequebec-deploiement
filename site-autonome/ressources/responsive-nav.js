(() => {
  const setup = (container, panel, label) => {
    if (!container || !panel || container.querySelector(':scope > .responsive-menu-toggle')) return;
    const button = document.createElement('button');
    const panelId = panel.id || `responsive-menu-${Math.random().toString(36).slice(2, 9)}`;
    panel.id = panelId;
    button.className = 'responsive-menu-toggle';
    button.type = 'button';
    button.setAttribute('aria-controls', panelId);
    button.setAttribute('aria-expanded', 'false');
    button.setAttribute('aria-label', `Ouvrir le menu ${label}`);
    button.innerHTML = '<span></span><span></span><span></span>';
    container.insertBefore(button, panel);

    const close = () => {
      container.classList.remove('responsive-menu-open');
      button.setAttribute('aria-expanded', 'false');
      button.setAttribute('aria-label', `Ouvrir le menu ${label}`);
    };
    button.addEventListener('click', () => {
      const open = container.classList.toggle('responsive-menu-open');
      button.setAttribute('aria-expanded', String(open));
      button.setAttribute('aria-label', `${open ? 'Fermer' : 'Ouvrir'} le menu ${label}`);
    });
    panel.querySelectorAll('a').forEach(link => link.addEventListener('click', close));
    document.addEventListener('keydown', event => { if (event.key === 'Escape') close(); });
    window.addEventListener('resize', () => { if (window.innerWidth > 900) close(); });
  };

  const home = document.querySelector('.mobile-nav');
  if (home) {
    const links = document.createElement('div');
    links.className = 'responsive-menu-links';
    [...home.children].filter(child => child.matches('a:not(.mobile-brand)')).forEach(link => links.append(link));
    home.append(links);
    setup(home, links, 'principal');
  }

  const local = document.querySelector('.local-mobile-nav');
  if (local) setup(local, local.querySelector(':scope > div'), 'principal');

  const header = document.querySelector('.site-header');
  if (header) setup(header, header.querySelector(':scope > nav'), 'principal');
})();
