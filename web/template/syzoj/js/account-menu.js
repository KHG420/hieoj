// Keep the downward menu outside the horizontally scrolling mobile navigation.
(() => {
  const account = document.querySelector('.oj-account-menu');
  if (!account) return;
  const nav = account.closest('.oj-topnav');
  function positionMenu() {
    if (window.innerWidth >= 1000) return;
    const bounds = account.getBoundingClientRect();
    account.style.setProperty('--oj-account-top', `${bounds.bottom}px`);
    account.style.setProperty('--oj-account-right', `${Math.min(window.innerWidth - 204, Math.max(8, window.innerWidth - bounds.right))}px`);
  }
  account.addEventListener('pointerenter', positionMenu);
  account.addEventListener('focusin', () => requestAnimationFrame(positionMenu));
  account.addEventListener('click', positionMenu);
  nav.addEventListener('scroll', positionMenu, {passive: true});
  window.addEventListener('resize', positionMenu, {passive: true});
  window.addEventListener('scroll', positionMenu, {passive: true});
  positionMenu();
})();
