(() => {
    const form = document.querySelector('#ads-form');
    const track = event => {
        if (document.cookie.split(';').some(item => item.trim() === 'zentrum-cookie-consent=accepted')) {
            window.gtag?.('event', event);
        }
    };
    track('ads_page_view');
    if (!form) return;
    const configuration = form.querySelector('fieldset');
    const custom = form.elements.custom_duration;
    const updateDuration = () => {
        custom.required = form.elements.duration.value === 'Personalizada';
        custom.disabled = !custom.required;
        custom.parentElement.hidden = !custom.required;
    };
    updateDuration();
    form.elements.duration.addEventListener('change', updateDuration);
    const summary = document.querySelector('#ads-summary');
    const next = document.querySelector('#ads-continue');
    configuration.addEventListener('input', () => {
        summary.textContent = '';
        next.hidden = true;
    });
    configuration.addEventListener('input', () => track('ads_simulator_start'), {once:true});
    next.addEventListener('click', event => {
        event.preventDefault();
        form.elements.company.focus();
    });
    const review = document.querySelector('#ads-review');
    review.hidden = false;
    review.addEventListener('click', () => {
        const values = [];
        for (const field of configuration.querySelectorAll('input,select')) {
            if (field.disabled) continue;
            if (!field.reportValidity()) return;
            if (field.value) values.push(field.labels[0].firstChild.textContent.trim() + ': ' + (field.selectedOptions?.[0]?.text || field.value));
        }
        summary.textContent = values.join(' · ');
        next.hidden = false;
        summary.focus();
        track('ads_simulator_complete');
    });
    document.querySelectorAll('[data-format]').forEach(link => link.addEventListener('click', event => {
        event.preventDefault();
        form.elements.format_id.value = link.dataset.format;
        form.elements.format_id.dispatchEvent(new Event('input', {bubbles:true}));
        form.scrollIntoView();
        form.elements.fleet.focus();
        track('ads_proposal_click');
    }));
})();
