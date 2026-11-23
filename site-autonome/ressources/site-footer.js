(() => {
  document.querySelector('.site-unified-footer')?.remove();
  const footer = document.createElement('footer');
  footer.className = 'site-unified-footer';
  footer.innerHTML = `
    <div class="site-footer-grid">
      <section>
        <p class="site-footer-brand">Photographie immobilière Québec</p>
        <p>Photographie, drone, vidéo et visite virtuelle pour mettre chaque propriété en valeur.</p>
      </section>
      <section>
        <p class="site-footer-label">Secteurs desservis</p>
        <p>Lévis · Québec · Centre-du-Québec · Bas-Saint-Laurent</p>
      </section>
      <section class="site-footer-contact">
        <p class="site-footer-label">Contact</p>
        <a href="mailto:fbergeron@photographie-immobiliere-quebec.com">fbergeron@photographie-immobiliere-quebec.com</a>
        <a href="tel:+15814437718">581-443-7718</a>
      </section>
    </div>
    <nav class="site-footer-legal" aria-label="Informations légales">
      <a href="${location.pathname.includes('/realisations/') ? '../' : ''}politique-confidentialite.html">Politique de confidentialité</a>
      <a href="${location.pathname.includes('/realisations/') ? '../' : ''}conditions-utilisation.html">Conditions d’utilisation</a>
      <p class="site-footer-copy">© ${new Date().getFullYear()} Photographie immobilière Québec</p>
    </nav>`;
  document.body.append(footer);
})();
