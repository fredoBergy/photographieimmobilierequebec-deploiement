/* Local behaviour for the original Wix server-rendered markup. No Wix API calls. */
(() => {
  const updateScrollbar = () => document.documentElement.style.setProperty('--scrollbar-width',
    Math.max(0, window.innerWidth - document.documentElement.clientWidth) + 'px');
  updateScrollbar();
  window.addEventListener('resize', updateScrollbar);
  new ResizeObserver(updateScrollbar).observe(document.documentElement);
  if (!document.body.classList.contains('home-page')) {
    const mobileNav = document.createElement('nav');
    mobileNav.className = 'local-mobile-nav';
    mobileNav.setAttribute('aria-label', 'Navigation mobile');
    mobileNav.innerHTML = '<a class="local-mobile-brand" href="index.html">PHOTOGRAPHIE IMMOBILIÈRE QUÉBEC</a><div><a href="index.html">Accueil</a><a href="a-propos.html">À propos</a><a href="services.html">Services</a><a href="dernieres-realisations.html">Réalisations</a><a href="tarifs.html">Tarifs</a><a href="contact.html">Contact</a></div>';
    const currentPage = location.pathname.split('/').pop() || 'index.html';
    mobileNav.querySelectorAll('a').forEach(link => {
      if (link.getAttribute('href') === currentPage) link.setAttribute('aria-current', 'page');
    });
    document.body.prepend(mobileNav);
  }
  const menu = document.querySelector('wix-dropdown-menu');
  if (menu) {
    const parent = menu.querySelector('li:has(ul)');
    const submenu = parent?.querySelector('ul');
    const trigger = parent?.querySelector('button');
    if (submenu && !submenu.querySelector('a[href="publicite-radio.html"]')) {
      const item = document.createElement('li');
      item.innerHTML = '<a data-testid="linkElement" href="publicite-radio.html" target="_self">PUBLICITÉ RADIO POUR COURTIERS</a>';
      submenu.append(item);
    }
    let closeTimer;
    if (submenu) document.documentElement.append(submenu);
    const placeSubmenu = () => {
      const rect = parent?.getBoundingClientRect();
      if (!rect || !submenu) return;
      submenu.style.setProperty('--local-submenu-left', `${rect.left + rect.width / 2}px`);
      submenu.style.setProperty('--local-submenu-top', `${rect.bottom}px`);
      submenu.style.setProperty('left', `${rect.left + rect.width / 2}px`, 'important');
      submenu.style.setProperty('top', `${rect.bottom}px`, 'important');
    };
    const setOpen = open => {
      clearTimeout(closeTimer);
      parent?.classList.toggle('local-open', open);
      submenu?.classList.toggle('local-open', open);
      submenu?.style.setProperty('display', open ? 'block' : 'none', 'important');
      submenu?.style.setProperty('visibility', open ? 'visible' : 'hidden', 'important');
      submenu?.style.setProperty('opacity', open ? '1' : '0', 'important');
      submenu?.style.setProperty('z-index', '2147483647', 'important');
      parent?.querySelector('a')?.setAttribute('aria-expanded', String(open));
      submenu?.setAttribute('aria-hidden', String(!open));
      if (open) placeSubmenu();
    };
    trigger?.addEventListener('click', e => { e.preventDefault(); e.stopPropagation(); setOpen(true); });
    parent?.addEventListener('mouseenter', () => setOpen(true));
    parent?.addEventListener('mouseleave', () => { closeTimer = setTimeout(() => setOpen(false), 80); });
    submenu?.addEventListener('mouseenter', () => setOpen(true));
    submenu?.addEventListener('mouseleave', () => setOpen(false));
    parent?.addEventListener('focusin', () => setOpen(true));
    parent?.addEventListener('focusout', e => { if (!parent.contains(e.relatedTarget) && !submenu?.contains(e.relatedTarget)) setOpen(false); });
    submenu?.addEventListener('focusout', e => { if (!submenu.contains(e.relatedTarget) && !parent?.contains(e.relatedTarget)) setOpen(false); });
    window.addEventListener('resize', () => { if (parent?.classList.contains('local-open')) placeSubmenu(); });
    window.addEventListener('scroll', () => { if (parent?.classList.contains('local-open')) placeSubmenu(); }, { passive: true });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') setOpen(false); });
  }
  document.querySelector('#SKIP_TO_CONTENT_BTN')?.addEventListener('click', () => {
    const main = document.querySelector('main'); main?.focus(); main?.scrollIntoView();
  });
  const contactForm = document.querySelector('[data-local-contact]');
  if (contactForm) {
    const button = contactForm.querySelector('button[type="submit"]');
    const label = button.querySelector('.wixui-button__label');
    const status = document.getElementById('comp-jxx0zcl1');
    const statusText = status.querySelector('.color_11') || status;
    const isPreview = ['localhost', '127.0.0.1', '::1'].includes(location.hostname);
    let csrf = '', preparing = null, sending = false;
    const feedback = (message, state) => {
      statusText.textContent = message;
      status.dataset.state = state;
      status.hidden = false;
      status.setAttribute('role', state === 'error' ? 'alert' : 'status');
    };
    const prepare = () => {
      if (!preparing) {
        preparing = fetch('contact-send.php?action=token', {
          credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' }
        }).then(async response => {
          const result = await response.json();
          if (!response.ok || !result.ok || !result.token) throw new Error('Préparation impossible.');
          csrf = result.token;
        }).finally(() => { preparing = null; });
      }
      return preparing;
    };
    if (!isPreview) prepare().catch(() => {});
    contactForm.addEventListener('submit', async event => {
      event.preventDefault();
      if (sending || !contactForm.reportValidity()) return;
      if (isPreview) {
        feedback('L’envoi est disponible sur le site en ligne, pas dans cet aperçu local.', 'error');
        return;
      }
      sending = true;
      button.disabled = true;
      button.setAttribute('aria-disabled', 'true');
      contactForm.setAttribute('aria-busy', 'true');
      label.textContent = 'Envoi…';
      status.hidden = true;
      try {
        if (!csrf) await prepare();
        const data = new URLSearchParams(new FormData(contactForm));
        data.set('csrf', csrf);
        const response = await fetch(contactForm.action, {
          method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json' }, body: data
        });
        const result = await response.json();
        if (!response.ok || !result.ok) {
          if (response.status === 403) csrf = '';
          throw new Error(result.message || 'Votre demande n’a pas pu être envoyée.');
        }
        contactForm.reset();
        csrf = '';
        feedback(result.message, 'success');
        prepare().catch(() => {});
      } catch (error) {
        feedback(error instanceof SyntaxError || error instanceof TypeError
          ? 'L’envoi est temporairement indisponible. Réessayez ou appelez au 581-443-7718.'
          : error.message, 'error');
      } finally {
        sending = false;
        button.disabled = false;
        button.setAttribute('aria-disabled', 'false');
        contactForm.removeAttribute('aria-busy');
        label.textContent = 'Envoyer';
      }
    });
  }
  document.querySelectorAll('wix-video video').forEach(video => {
    video.muted = true;
    const poster = video.parentElement.querySelector('wow-image');
    video.addEventListener('playing', () => {
      video.classList.add('local-video-playing');
      if (poster) poster.style.display = 'none';
    });
    video.play().catch(() => {});
  });
  document.querySelectorAll('[data-local-inline-player]').forEach(container => {
    const video = container.querySelector('video');
    const button = container.querySelector('button');
    button.addEventListener('click', () => {
      video.controls = true;
      video.play().catch(() => { video.controls = false; button.hidden = false; });
    });
    video.addEventListener('playing', () => { button.hidden = true; });
    video.addEventListener('error', () => { button.hidden = false; });
  });
  // Preserve meaningful alternative text in the legacy photo galleries.
  document.querySelectorAll('img[alt=""]').forEach((image, index) => {
    if (image.closest('dialog') || image.getAttribute('aria-hidden') === 'true') return;
    const tile = image.closest('[data-local-gallery]');
    if (tile) {
      try {
        const data = JSON.parse(document.getElementById(`local-gallery-${tile.dataset.localGallery}`)?.textContent || '[]');
        image.alt = data[Number(tile.dataset.localIndex)]?.title || `Photographie professionnelle ${index + 1}`;
      } catch (_) {
        image.alt = `Photographie professionnelle ${index + 1}`;
      }
      return;
    }
    image.alt = document.title.split('|')[0].trim() || `Photographie professionnelle ${index + 1}`;
  });
  // The video gallery stretches with the viewport. Wix supplied only a fixed SSR layout.
  const resizeVideoGallery = () => {
    const wrapper = document.getElementById('gallery-wrapper-comp-ltol37h2');
    if (!wrapper) return;
    const width = wrapper.getBoundingClientRect().width;
    const leftWidth = Math.ceil((width - 20) / 2);
    const rightWidth = Math.floor((width - 20) / 2);
    const leftHeight = Math.round(leftWidth * 9 / 16);
    const rightHeight = Math.round(rightWidth * 9 / 16);
    const cells = [[0,0,leftWidth,leftHeight],[leftWidth+20,0,rightWidth,rightHeight],
      [leftWidth+20,Math.max(leftHeight,rightHeight)+20,rightWidth,rightHeight],
      [0,Math.max(leftHeight,rightHeight)+20,leftWidth,leftHeight]];
    wrapper.querySelectorAll('[data-local-gallery]').forEach((tile, i) => {
      const [left,top,w,h] = cells[i];
      Object.assign(tile.style,{left:left+'px',top:top+'px',width:w+'px',height:h+'px'});
      tile.querySelectorAll('[data-hook="item-wrapper"],[data-hook="image-item"],picture,img').forEach(el => {
        Object.assign(el.style,{width:w+'px',height:h+'px',margin:'0',objectFit:'cover'});
        if (el.tagName === 'PICTURE') el.style.display = 'block';
      });
      tile.querySelectorAll('.item-hover-flex-container').forEach(el => {
        Object.assign(el.style,{width:w+'px',height:h+'px'});
      });
    });
    const height = Math.max(leftHeight,rightHeight) * 2 + 20;
    const container = document.getElementById('pro-gallery-container-comp-ltol37h2');
    const margin = document.getElementById('pro-gallery-margin-container-comp-ltol37h2');
    container.style.height = height+'px'; margin.style.height = height+'px'; margin.style.width = width+'px';
  };
  resizeVideoGallery();
  window.addEventListener('resize',resizeVideoGallery);
  let currentGallery = [], currentIndex = 0, previousFocus;
  const modal = document.createElement('dialog');
  modal.className = 'local-dialog';
  modal.setAttribute('aria-label', 'Galerie');
  modal.innerHTML = '<button class="local-close" aria-label="Fermer">×</button><button class="local-prev" aria-label="Précédent">‹</button><div class="local-media"></div><button class="local-next" aria-label="Suivant">›</button><p class="local-caption"></p>';
  document.body.append(modal);
  const render = () => {
    const media = modal.querySelector('.local-media');
    media.replaceChildren();
    const item = currentGallery[currentIndex];
    if (item.kind === 'external') {
      const p = document.createElement('p'); p.className = 'local-external';
      p.append('Vidéo YouTube. ');
      const a = document.createElement('a'); a.href = item.src; a.target = '_blank'; a.rel = 'noopener';
      a.textContent = 'Ouvrir la vidéo'; p.append(a); media.append(p);
    } else {
      const el = document.createElement(item.kind === 'video' ? 'video' : 'img');
      el.src = item.src;
      if (item.kind === 'video') { el.controls = true; el.autoplay = true; el.playsInline = true; el.poster = item.poster; }
      else el.alt = item.title;
      media.append(el);
    }
    modal.querySelector('.local-caption').textContent = `${currentIndex + 1} / ${currentGallery.length}`;
  };
  const move = direction => { currentIndex = (currentIndex + direction + currentGallery.length) % currentGallery.length; render(); };
  const close = () => { modal.querySelector('video')?.pause(); modal.close(); previousFocus?.focus(); };
  modal.querySelector('.local-close').addEventListener('click', close);
  modal.querySelector('.local-prev').addEventListener('click', () => move(-1));
  modal.querySelector('.local-next').addEventListener('click', () => move(1));
  modal.addEventListener('close', () => { modal.querySelector('video')?.pause(); previousFocus?.focus(); });
  modal.addEventListener('keydown', e => { if (e.key === 'ArrowRight') move(1); if (e.key === 'ArrowLeft') move(-1); });
  document.querySelectorAll('[data-local-gallery]').forEach(tile => {
    const open = () => {
      currentGallery = JSON.parse(document.getElementById('local-gallery-' + tile.dataset.localGallery).textContent);
      currentIndex = Number(tile.dataset.localIndex); previousFocus = tile; render(); modal.showModal();
    };
    tile.addEventListener('click', open);
    tile.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(); } });
  });
})();
