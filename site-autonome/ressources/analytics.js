(() => {
  const measurementId = 'G-1MKDW1FW88';
  const storageKey = 'photographie_analytics_consent';
  let loaded = false;

  const loadAnalytics = () => {
    if (loaded) return;
    loaded = true;
    window.dataLayer = window.dataLayer || [];
    window.gtag = function () { window.dataLayer.push(arguments); };
    window.gtag('js', new Date());
    window.gtag('config', measurementId, { anonymize_ip: true });
    const script = document.createElement('script');
    script.async = true;
    script.src = `https://www.googletagmanager.com/gtag/js?id=${measurementId}`;
    document.head.append(script);
  };

  const removeBanner = () => document.querySelector('.analytics-consent')?.remove();
  const choose = value => {
    localStorage.setItem(storageKey, value);
    removeBanner();
    if (value === 'accepted') loadAnalytics();
  };
  const showBanner = () => {
    removeBanner();
    const banner = document.createElement('section');
    banner.className = 'analytics-consent';
    banner.setAttribute('role', 'dialog');
    banner.setAttribute('aria-label', 'Choix des témoins de mesure d’audience');
    banner.innerHTML = '<div><p><strong>Mesure d’audience</strong><br>Avec votre accord, Google Analytics nous aide à comprendre quelles pages sont consultées. Aucun outil publicitaire n’est activé. <a href="/politique-confidentialite.html">En savoir plus</a></p><p class="analytics-actions"><button type="button" data-consent="refused">Refuser</button><button type="button" data-consent="accepted">Accepter</button></p></div>';
    Object.assign(banner.style, { position:'fixed', left:'0', right:'0', bottom:'0', zIndex:'2147483647', background:'#242424', color:'#fff', padding:'18px 24px', boxShadow:'0 -4px 20px rgba(0,0,0,.25)', font:'15px/1.5 Arial,sans-serif' });
    const inner = banner.firstElementChild;
    Object.assign(inner.style, { maxWidth:'980px', margin:'auto', display:'flex', alignItems:'center', justifyContent:'space-between', gap:'22px', flexWrap:'wrap' });
    banner.querySelectorAll('p').forEach(p => Object.assign(p.style, { margin:'0' }));
    banner.querySelector('a').style.color = '#e0b54c';
    banner.querySelectorAll('button').forEach(button => Object.assign(button.style, { border:'1px solid #d6aa3d', background:button.dataset.consent === 'accepted' ? '#d6aa3d' : 'transparent', color:button.dataset.consent === 'accepted' ? '#242424' : '#fff', padding:'10px 18px', marginLeft:'10px', cursor:'pointer', font:'inherit' }));
    banner.addEventListener('click', event => {
      const value = event.target.dataset?.consent;
      if (value) choose(value);
    });
    document.body.append(banner);
  };

  document.addEventListener('DOMContentLoaded', () => {
    const consent = localStorage.getItem(storageKey);
    if (consent === 'accepted') loadAnalytics();
    else if (consent !== 'refused') showBanner();
    document.querySelectorAll('[data-analytics-settings]').forEach(link => link.addEventListener('click', event => { event.preventDefault(); showBanner(); }));
  });
})();
