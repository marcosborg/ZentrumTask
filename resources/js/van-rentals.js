const root = document.querySelector('[data-van-rental]');
if (root) {
    const form = root.querySelector('[data-reservation-form]');
    const mode = form.elements.mode;
    const start = form.elements.starts_at;
    const end = form.elements.ends_at;
    const submit = form.querySelector('[data-submit]');
    const estimate = form.querySelector('[data-estimate]');
    let quoteController;
    let calendarController;
    let periods = [];
    const money = (cents) => new Intl.NumberFormat('pt-PT', { style: 'currency', currency: 'EUR' }).format(cents / 100);
    const localTime = (date) => new Intl.DateTimeFormat('pt-PT', { timeZone: 'Europe/Lisbon', day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }).format(new Date(date));
    const localDay = (date) => new Intl.DateTimeFormat('en-CA', { timeZone: 'Europe/Lisbon', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date(date));
    const error = (field, value) => { const target = form.querySelector(`[data-error="${field}"]`); if (target) target.textContent = value; };
    async function quote() {
        quoteController?.abort();
        quoteController = new AbortController();
        submit.disabled = true;
        ['mode', 'starts_at', 'ends_at'].forEach((field) => error(field, ''));
        if (!start.value || !end.value) { estimate.textContent = 'Escolha as datas para calcular a estimativa.'; return; }
        estimate.textContent = 'A verificar disponibilidade e preço…';
        try {
            const response = await fetch(`${root.dataset.quote}?${new URLSearchParams({mode: mode.value, starts_at: start.value, ends_at: end.value})}`, {headers: {Accept: 'application/json'}, signal: quoteController.signal});
            const data = await response.json();
            if (!response.ok) {
                Object.entries(data.errors || {}).forEach(([field, messages]) => error(field, messages[0]));
                estimate.textContent = response.status === 422 ? 'Reveja a modalidade e os horários selecionados.' : 'Não foi possível consultar a disponibilidade. Tente novamente.';
                return;
            }
            estimate.textContent = `${data.billable_hours} h × ${money(data.hourly_rate)} = ${money(data.estimated_total)} (IVA incluído). Caução separada: ${money(data.deposit)}. Extras sob orçamento. Sujeito a confirmação.`;
            submit.disabled = false;
        } catch (exception) {
            if (exception.name !== 'AbortError') estimate.textContent = 'Sem ligação. Volte a selecionar o horário para tentar novamente.';
        }
    }
    function updateMode() {
        const driver = mode.value === 'with_driver';
        root.querySelector('[data-driver-fields]').hidden = !driver;
        root.querySelector('[data-pickup]').hidden = driver;
        ['origin', 'destination'].forEach((name) => { form.elements[name].required = driver; form.elements[name].disabled = !driver; });
        form.elements.loading_help.disabled = !driver;
        quote();
    }
    mode.addEventListener('change', updateMode);
    [start, end].forEach((input) => input.addEventListener('change', quote));
    form.addEventListener('submit', () => { submit.disabled = true; submit.textContent = 'A enviar pedido…'; });
    window.addEventListener('pageshow', () => { submit.textContent = 'Enviar pedido de reserva ↗'; quote(); });
    root.querySelectorAll('[data-gallery-photo]').forEach((button) => button.addEventListener('click', () => { root.querySelector('[data-main-photo]').src = button.dataset.galleryPhoto; }));
    const month = root.querySelector('[data-calendar-month]');
    const calendar = root.querySelector('[data-calendar]');
    const status = root.querySelector('[data-calendar-status]');
    const details = root.querySelector('[data-day-details]');
    async function loadCalendar() {
        calendarController?.abort();
        calendarController = new AbortController();
        calendar.replaceChildren();
        details.hidden = true;
        status.textContent = 'A consultar o calendário…';
        try {
            const response = await fetch(`${root.dataset.availability}?${new URLSearchParams({month: month.value})}`, {headers: {Accept: 'application/json'}, signal: calendarController.signal});
            if (!response.ok) throw new Error('calendar');
            periods = (await response.json()).periods;
            const [year, m] = month.value.split('-').map(Number);
            const firstDay = (new Date(Date.UTC(year, m - 1, 1)).getUTCDay() + 6) % 7;
            const count = new Date(Date.UTC(year, m, 0)).getUTCDate();
            for (let i = 0; i < firstDay; i++) calendar.append(document.createElement('span'));
            for (let day = 1; day <= count; day++) {
                const value = `${month.value}-${String(day).padStart(2, '0')}`;
                const busy = periods.filter((period) => localDay(period.start) <= value && localDay(new Date(new Date(period.end).getTime() - 1)) >= value);
                const button = document.createElement('button');
                button.type = 'button';
                button.textContent = String(day);
                button.dataset.busy = busy.length > 0;
                button.disabled = value < root.dataset.today;
                button.setAttribute('aria-label', `${day}/${m}/${year}${busy.length ? ', com períodos ocupados' : ', consultar horários'}`);
                button.setAttribute('aria-pressed', 'false');
                if (busy.length) { const label = document.createElement('small'); label.textContent = 'Ocupação'; button.append(label); }
                button.addEventListener('click', () => {
                    calendar.querySelectorAll('button').forEach((item) => item.setAttribute('aria-pressed', 'false'));
                    button.setAttribute('aria-pressed', 'true');
                    details.hidden = false;
                    details.textContent = busy.length ? `Períodos indisponíveis: ${busy.map((p) => `${localTime(p.start)} → ${localTime(p.end)}`).join('; ')}. Consulte uma estimativa para validar o horário.` : 'Sem reservas confirmadas neste dia. Escolha o início e o fim para verificar o horário exato.';
                    start.value = `${value}T${root.dataset.opens}`;
                    if (!end.value || end.value <= start.value) end.value = '';
                    quote();
                });
                calendar.append(button);
            }
            status.textContent = 'Disponibilidade sujeita a confirmação. Os dados dos clientes são privados.';
        } catch (exception) { if (exception.name !== 'AbortError') status.textContent = 'Não foi possível carregar o calendário. Pode consultar o horário através da estimativa.'; }
    }
    month.addEventListener('change', loadCalendar);
    updateMode();
    loadCalendar();
}
