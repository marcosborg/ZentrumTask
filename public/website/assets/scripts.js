document.addEventListener('DOMContentLoaded', () => {
  const consentKey = 'zentrum-cookie-consent';
  const banner = document.querySelector('[data-cookie-banner]');

  if (!banner) {
    return;
  }

  const acceptButton = banner.querySelector('[data-cookie-accept]');

  const hasConsent = () => {
    try {
      return localStorage.getItem(consentKey) === 'accepted';
    } catch (error) {
      console.warn('Storage indisponivel para consentimento de cookies.', error);
      return false;
    }
  };

  const saveConsent = () => {
    try {
      localStorage.setItem(consentKey, 'accepted');
    } catch (error) {
      console.warn('Nao foi possivel guardar o consentimento de cookies.', error);
    }
  };

  const hideBanner = () => {
    banner.classList.add('cookie-banner--hidden');
  };

  if (hasConsent()) {
    hideBanner();
    return;
  }

  if (acceptButton) {
    acceptButton.addEventListener('click', () => {
      saveConsent();
      hideBanner();
    });
  }
});
