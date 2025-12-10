document.addEventListener('DOMContentLoaded', () => {
  const consentKey = 'zentrum-cookie-consent';
  const banner = document.querySelector('[data-cookie-banner]');

  if (!banner) {
    return;
  }

  const acceptButton = banner.querySelector('[data-cookie-accept]');

  const hasConsent = () => {
    try {
      if (localStorage.getItem(consentKey) === 'accepted') {
        return true;
      }
    } catch (error) {
      console.warn('Storage indisponivel para consentimento de cookies.', error);
    }

    return document.cookie.split(';').some((cookie) => cookie.trim().startsWith(`${consentKey}=accepted`));
  };

  const saveConsent = () => {
    try {
      localStorage.setItem(consentKey, 'accepted');
    } catch (error) {
      console.warn('Nao foi possivel guardar o consentimento de cookies.', error);
    }

    document.cookie = `${consentKey}=accepted; path=/; max-age=${60 * 60 * 24 * 365}`;
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
