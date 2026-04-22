@extends('website.layout')

@section('title', 'Reserva de viatura | Zentrum TVDE')

@push('styles')
<style>
.wizard-hero {
    background: #eeeeee;
    color: #0f172a;
    padding-top: 0;
}
.wizard-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
}
.wizard-panel {
    background: #f9fbff;
    border: 1px solid #d9e2ec;
    color: #0f172a;
}
.wizard-label {
    color: #0f172a !important;
    font-weight: 600;
}
.wizard-progress {
    height: 10px;
    background: #e2e8f0;
    border-radius: 999px;
    overflow: hidden;
    }
    .wizard-progress .bar {
        height: 100%;
        background: linear-gradient(90deg, #22c55e, #0ea5e9);
        transition: width 0.25s ease;
    }
    .badge-pill {
        padding: 0.45rem 0.8rem;
        border-radius: 999px;
        font-weight: 600;
    }
.wizard-hero .text-muted {
    color: #475569 !important;
}
.wizard-panel p,
.wizard-panel li {
    color: #475569;
}
.wizard-panel ul {
    margin-bottom: 0;
}
.wizard-hero .form-check-label,
.wizard-hero .form-label,
.wizard-hero .form-control,
.wizard-hero .form-select {
    color: #0f172a;
}
.wizard-hero ::placeholder {
    color: #94a3b8;
}
.wizard-check-grid {
    display: grid;
    gap: 0.9rem;
}
.wizard-check-card {
    position: relative;
    display: flex;
    align-items: flex-start;
    gap: 0.9rem;
    padding: 1rem 1.1rem;
    border: 1px solid #d9e2ec;
    border-radius: 18px;
    background: #ffffff;
    box-shadow: 0 6px 18px rgba(15, 23, 42, 0.04);
    cursor: pointer;
    transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease, background 0.2s ease;
}
.wizard-check-card:hover {
    border-color: #93c5fd;
    box-shadow: 0 10px 24px rgba(37, 99, 235, 0.10);
    transform: translateY(-1px);
}
.wizard-check-card.is-active {
    border-color: #2563eb;
    background: linear-gradient(180deg, #eff6ff 0%, #f8fbff 100%);
    box-shadow: 0 12px 28px rgba(37, 99, 235, 0.14);
}
.wizard-check-input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}
.wizard-check-indicator {
    width: 26px;
    height: 26px;
    border-radius: 999px;
    border: 2px solid #cbd5e1;
    background: #fff;
    flex: 0 0 26px;
    margin-top: 0.05rem;
    position: relative;
    transition: border-color 0.2s ease, background 0.2s ease, transform 0.2s ease;
}
.wizard-check-card.is-active .wizard-check-indicator {
    border-color: #2563eb;
    background: #2563eb;
    transform: scale(1.03);
}
.wizard-check-card.is-active .wizard-check-indicator::after {
    content: '';
    position: absolute;
    left: 7px;
    top: 3px;
    width: 7px;
    height: 12px;
    border: solid #fff;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
}
.wizard-check-copy {
    min-width: 0;
}
.wizard-check-title {
    display: block;
    color: #0f172a;
    font-weight: 700;
    line-height: 1.35;
}
.wizard-check-hint {
    display: block;
    margin-top: 0.25rem;
    color: #64748b;
    font-size: 0.92rem;
    line-height: 1.5;
}
.wizard-check-grid--inline {
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
}
.wizard-info-banner {
    background: linear-gradient(180deg, #eff6ff 0%, #f8fbff 100%);
    border: 1px solid #bfdbfe;
    border-radius: 18px;
    padding: 1rem 1.1rem;
}
.wizard-info-banner strong {
    color: #0f172a;
}
.wizard-tax-link {
    display: inline-block;
    margin: 0;
    padding: 0;
    border: none;
    background: transparent;
    color: #1d4ed8;
    font-weight: 800;
    text-decoration: none;
    line-height: 1;
    vertical-align: super;
    font-size: 0.8em;
    cursor: pointer;
}
.wizard-tax-popover {
    position: relative;
    display: inline-flex;
    align-items: flex-start;
}
.wizard-tax-popover__bubble {
    position: absolute;
    left: 50%;
    bottom: calc(100% + 0.65rem);
    transform: translateX(-50%);
    width: min(320px, 72vw);
    padding: 0.8rem 0.9rem;
    border-radius: 14px;
    background: #0f172a;
    color: #fff;
    font-size: 0.88rem;
    line-height: 1.55;
    box-shadow: 0 18px 32px rgba(15, 23, 42, 0.22);
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.18s ease, transform 0.18s ease;
    z-index: 10;
}
.wizard-tax-popover__bubble::after {
    content: '';
    position: absolute;
    left: 50%;
    top: 100%;
    transform: translateX(-50%);
    border-width: 7px 6px 0 6px;
    border-style: solid;
    border-color: #0f172a transparent transparent transparent;
}
.wizard-tax-popover:hover .wizard-tax-popover__bubble,
.wizard-tax-popover:focus-within .wizard-tax-popover__bubble {
    opacity: 1;
    transform: translateX(-50%) translateY(-2px);
}
.wizard-payment-grid {
    display: grid;
    gap: 0.9rem;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
}
.wizard-payment-grid--secondary {
    margin-top: 0.9rem;
}
.wizard-payment-summary {
    padding: 1.15rem 1.2rem;
    border-radius: 22px;
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    border: 1px solid #0f172a;
    box-shadow: 0 16px 30px rgba(15, 23, 42, 0.18);
    color: #fff;
}
.wizard-payment-summary__row + .wizard-payment-summary__row {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.12);
}
.wizard-payment-summary__label {
    display: block;
    margin-bottom: 0.2rem;
    color: rgba(255, 255, 255, 0.72);
    font-size: 0.82rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.wizard-payment-summary__value {
    display: block;
    color: #fff;
    font-size: 1.8rem;
    font-weight: 800;
    line-height: 1.15;
    letter-spacing: 0.02em;
}
.wizard-payment-summary__meta {
    display: block;
    margin-top: 0.35rem;
    color: rgba(255, 255, 255, 0.74);
    font-size: 0.92rem;
    line-height: 1.45;
}
.wizard-payment-card {
    padding: 1rem 1.1rem;
    border-radius: 18px;
    background: linear-gradient(180deg, #eff6ff 0%, #f8fbff 100%);
    border: 1px solid #bfdbfe;
    box-shadow: 0 8px 22px rgba(37, 99, 235, 0.08);
}
.wizard-payment-card span {
    display: block;
    margin-bottom: 0.25rem;
    color: #64748b;
    font-size: 0.82rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.wizard-payment-card strong {
    color: #0f172a;
    font-size: 1.3rem;
    font-weight: 800;
    line-height: 1.2;
}
.wizard-payment-card__meta {
    display: block;
    margin-top: 0.45rem;
    color: #64748b;
    font-size: 0.9rem;
    line-height: 1.45;
}
</style>
@endpush

@push('scripts')
<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script>
    function wizard(config) {
        return {
            steps: [
                { name: 'welcome', title: 'Como funciona a reserva', subtitle: 'Processo Zentrum TVDE' },
                { name: 'vehicle', title: 'Viatura', subtitle: 'Selecione a viatura pretendida' },
                { name: 'rental', title: 'Condições da reserva', subtitle: 'Caução, aluguer e levantamento' },
                { name: 'eligibility', title: 'Perfil TVDE', subtitle: 'Validação inicial' },
                { name: 'personal', title: 'Dados da reserva', subtitle: 'Contacto e identificação' },
                { name: 'documents', title: 'Documentos opcionais', subtitle: 'Envio para acelerar a reserva' },
                { name: 'summary', title: 'Confirmar reserva', subtitle: 'Revisão final' },
                { name: 'payment', title: 'Pagamento da caução', subtitle: 'Referência Multibanco' },
            ],
            vehicleTypes: config.vehicleTypes || [],
            preselectedVehicle: config.preselectedVehicle || null,
            documentFields: [
                { field: 'document_id', label: 'Documento de identificação' },
                { field: 'driver_license', label: 'Carta de condução' },
                { field: 'tvde_certificate', label: 'Certificado TVDE' },
                { field: 'criminal_record', label: 'Registo criminal' },
            ],
            form: {},
            documents: {},
            payment: {},
            token: config.token,
            saveEndpoint: config.saveEndpoint,
            uploadEndpoint: config.uploadEndpoint,
            submitEndpoint: config.submitEndpoint,
            paymentEndpoint: config.paymentEndpoint,
            stepIndex: 0,
            saveMessage: '',
            status: config.initial.status,
            paymentLoading: false,

            get progress() {
                return Math.round(((this.stepIndex + 1) / this.steps.length) * 100);
            },

            get statusLabel() {
                return this.status === 'submitted' ? 'Reserva enviada' : (this.status === 'incomplete' ? 'Em preenchimento' : 'Rascunho');
            },

            init() {
                this.form = {
                    accepts_model: Boolean(config.initial.accepts_model),
                    independent_driver: Boolean(config.initial.independent_driver),
                    rental_terms_read: Boolean(config.initial.rental_terms_read),
                    rental_terms_accept: Boolean(config.initial.rental_terms_accept),
                    has_tvde_course: config.initial.has_tvde_course ?? '',
                    certificate_valid: config.initial.certificate_valid ?? '',
                    experience: config.initial.experience ?? '',
                    platforms: config.initial.platforms ?? [],
                    full_name: config.initial.full_name ?? '',
                    email: config.initial.email ?? '',
                    phone: config.initial.phone ?? '',
                    nif: config.initial.nif ?? '',
                    iban: config.initial.iban ?? '',
                    vehicle_type_id: config.initial.vehicle_type_id ?? '',
                    rgpd: Boolean(config.initial.rgpd),
                    truth_declaration: Boolean(config.initial.truth_declaration),
                    contact_authorization: Boolean(config.initial.contact_authorization),
                };
                this.documents = config.initial.documents ?? {};
                this.payment = config.initialPayment ?? {};
                this.documentFields.forEach((doc) => {
                    this.documents[doc.field] = this.normalizeDocumentList(this.documents[doc.field]);
                });
                const initialStep = (config.initial.current_step || 'welcome') === 'legal'
                    ? 'summary'
                    : (config.initial.current_step || 'welcome');
                const foundStep = this.steps.findIndex((s) => s.name === initialStep);
                this.stepIndex = foundStep >= 0 ? foundStep : 0;

                if (this.steps[this.stepIndex]?.name === 'payment') {
                    this.loadPaymentReference();
                }
            },

            normalizeDocumentList(value) {
                if (!value) {
                    return [];
                }

                if (Array.isArray(value)) {
                    if (value.length === 0) {
                        return [];
                    }

                    if (typeof value[0] === 'string') {
                        return value.map((path) => ({
                            path,
                            name: (path || '').split('/').pop(),
                        }));
                    }

                    if (typeof value[0] === 'object') {
                        return value;
                    }

                    return [];
                }

                if (typeof value === 'string') {
                    return [{
                        path: value,
                        name: (value || '').split('/').pop(),
                    }];
                }

                if (typeof value === 'object') {
                    return [value];
                }

                return [];
            },

            validateStep(step) {
                if (step === 'welcome') {
                    return this.form.accepts_model && this.form.independent_driver;
                }
                if (step === 'rental') {
                    return this.form.rental_terms_read && this.form.rental_terms_accept;
                }
                if (step === 'vehicle') {
                    return Boolean(this.form.vehicle_type_id);
                }
                if (step === 'eligibility') {
                    return (
                        this.form.has_tvde_course !== '' &&
                        this.form.certificate_valid !== '' &&
                        (this.form.experience || '').trim() !== '' &&
                        Array.isArray(this.form.platforms) &&
                        this.form.platforms.length > 0
                    );
                }
                if (step === 'personal') {
                    return (
                        (this.form.full_name || '').trim() !== '' &&
                        (this.form.email || '').trim() !== '' &&
                        (this.form.phone || '').trim() !== '' &&
                        (this.form.nif || '').trim() !== '' &&
                        (this.form.iban || '').trim() !== ''
                    );
                }
                if (step === 'documents') {
                    return true;
                }
                if (step === 'summary') {
                    return this.form.rgpd && this.form.truth_declaration && this.form.contact_authorization;
                }
                if (step === 'payment') {
                    return true;
                }

                return true;
            },

            async saveCurrentStep() {
                const step = this.steps[this.stepIndex].name;

                if (!this.validateStep(step)) {
                    this.saveMessage = 'Preencha todos os campos obrigatórios';

                    return false;
                }

                this.saveMessage = 'A guardar reserva...';
                const payload = { ...this.form, step, token: this.token };
                try {
                    const res = await fetch(this.saveEndpoint, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        },
                        body: JSON.stringify(payload),
                    });
                    if (!res.ok) throw new Error('Erro ao guardar');
                    this.saveMessage = 'Dados da reserva guardados';
                    this.status = this.status === 'submitted' ? 'submitted' : 'incomplete';
                    return true;
                } catch (e) {
                    this.saveMessage = 'Erro ao guardar a reserva';
                    return false;
                }
            },

            async nextStep() {
                const saved = await this.saveCurrentStep();
                if (!saved) return;

                if (this.stepIndex < this.steps.length - 1) {
                    this.stepIndex += 1;
                }

                if (this.steps[this.stepIndex]?.name === 'payment') {
                    await this.loadPaymentReference();
                }
            },

            async prevStep() {
                if (this.stepIndex === 0) return;
                this.stepIndex -= 1;
            },

            async uploadFiles(files, field) {
                if (!files?.length) return;

                for (const file of files) {
                    const formData = new FormData();
                    formData.append('file', file);
                    formData.append('field', field);
                    formData.append('token', this.token);
                    formData.append('_token', '{{ csrf_token() }}');
                    this.saveMessage = 'A enviar ficheiro...';
                    const res = await fetch(this.uploadEndpoint, {
                        method: 'POST',
                        body: formData,
                    });
                    if (res.ok) {
                        const json = await res.json();
                        if (!Array.isArray(this.documents[field])) {
                            this.documents[field] = [];
                        }
                        this.documents[field].push(json.document);
                        this.saveMessage = 'Ficheiro enviado';
                    } else {
                        this.saveMessage = 'Erro no upload';
                    }
                }
            },

            async uploadFile(event, field, fileOverride = null) {
                const files = fileOverride ? [fileOverride] : Array.from(event?.target?.files ?? []);

                await this.uploadFiles(files, field);
            },

            handleDrop(event, field) {
                event.preventDefault();
                const files = Array.from(event.dataTransfer?.files ?? []);
                this.uploadFiles(files, field);
            },

            async submit() {
                // Validate all steps before submit
                const allValid = this.steps.every((s) => this.validateStep(s.name));
                if (!allValid) {
                    this.saveMessage = 'Preencha todos os campos obrigatórios antes de enviar a reserva';

                    return;
                }

                const saved = await this.saveCurrentStep();
                if (!saved) return;

                this.saveMessage = 'A enviar reserva...';
                const payload = { ...this.form, token: this.token };
                const res = await fetch(this.submitEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify(payload),
                });
                if (!res.ok) {
                    this.saveMessage = 'Erro ao enviar a reserva';
                    return;
                }
                this.status = 'submitted';
                this.saveMessage = 'Reserva enviada com sucesso. Será redirecionado em 5 segundos...';

                let seconds = 5;
                const interval = setInterval(() => {
                    seconds -= 1;
                    this.saveMessage = `Reserva enviada com sucesso. Será redirecionado em ${seconds} segundos...`;
                    if (seconds <= 0) {
                        clearInterval(interval);
                        window.location.href = '/';
                    }
                }, 1000);
            },

            async loadPaymentReference(force = false) {
                if (this.paymentLoading) {
                    return;
                }

                if (!force && this.payment?.reference) {
                    return;
                }

                this.paymentLoading = true;
                this.saveMessage = 'A preparar referência Multibanco...';

                try {
                    const res = await fetch(this.paymentEndpoint, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        },
                        body: JSON.stringify({ token: this.token }),
                    });

                    if (!res.ok) {
                        throw new Error('Erro ao gerar referência');
                    }

                    const json = await res.json();
                    this.payment = json.payment || {};
                    this.saveMessage = this.payment?.message || 'Referência preparada.';
                } catch (e) {
                    this.saveMessage = 'Não foi possível preparar a referência Multibanco.';
                } finally {
                    this.paymentLoading = false;
                }
            },
        };
    }
</script>
@endpush

@section('content')
<section class="wizard-hero pt-0 pb-5">
    <div class="container-fluid px-0">
        <div class="w-100 mb-4">
            <img
                src="{{ asset('website/assets/header-candidatura.png') }}"
                alt="Reserva de viatura Zentrum TVDE"
                class="img-fluid w-100"
                style="object-fit: cover; max-height: 380px;"
            >
        </div>
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                <div
                    x-data="wizard({
                        token: '{{ $application->token }}',
                        initial: @js($application->toArray()),
                        vehicleTypes: @js($vehicleTypes),
                        preselectedVehicle: @js($preselectedVehicle ? [
                            'id' => $preselectedVehicle->id,
                            'label' => $preselectedVehicle->displayName(),
                        ] : null),
                        initialPayment: @js($initialPayment),
                        uploadEndpoint: '{{ $uploadEndpoint }}',
                        saveEndpoint: '{{ $saveEndpoint }}',
                        submitEndpoint: '{{ $submitEndpoint }}',
                        paymentEndpoint: '{{ $paymentEndpoint }}'
                    })"
                    x-init="init()"
                >
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <p class="mb-1 text-muted">Zentrum TVDE</p>
                            <h1 class="h3 mb-0">Reserva de viatura</h1>
                        </div>
                        <span class="badge bg-success badge-pill" x-text="statusLabel"></span>
                    </div>

                    <div class="wizard-card rounded-4 p-4 mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small class="text-muted">Passo <span x-text="stepIndex + 1"></span> de <span x-text="steps.length"></span></small>
                            <small class="text-muted" x-text="steps[stepIndex].title"></small>
                        </div>
                        <div class="wizard-progress mb-3">
                            <div class="bar" :style="`width: ${progress}%`"></div>
                        </div>

                        <template x-for="(s, idx) in steps" :key="s.name">
                            <section x-show="stepIndex === idx" x-transition class="mt-3">
                                <div class="d-flex align-items-center gap-3 mb-3">
                                    <div class="rounded-circle bg-success bg-opacity-25 text-success fw-bold d-flex align-items-center justify-content-center" style="width:48px; height:48px;">
                                        <span x-text="idx + 1"></span>
                                    </div>
                                    <div>
                                        <h2 class="h5 mb-0" x-text="s.title"></h2>
                                        <p class="mb-0 text-muted" x-text="s.subtitle"></p>
                                    </div>
                                </div>

                                <div class="row g-3">
                                    <template x-if="s.name === 'welcome'">
                                        <div class="col-12">
                                            <p class="text-muted">Está a iniciar um pedido de reserva. Vamos recolher os dados essenciais para lhe apresentar os próximos passos e preparar a viatura.</p>
                                            <div class="wizard-check-grid mt-3">
                                                <label class="wizard-check-card" :class="{ 'is-active': form.accepts_model }" for="accepts_model">
                                                    <input class="wizard-check-input" type="checkbox" x-model="form.accepts_model" id="accepts_model">
                                                    <span class="wizard-check-indicator" aria-hidden="true"></span>
                                                    <span class="wizard-check-copy">
                                                        <span class="wizard-check-title">Compreendo como funciona a reserva</span>
                                                        <span class="wizard-check-hint">A Zentrum TVDE disponibiliza a viatura em regime profissional e esta reserva serve para avançar para validação e preparação do processo.</span>
                                                    </span>
                                                </label>
                                                <label class="wizard-check-card" :class="{ 'is-active': form.independent_driver }" for="independent_driver">
                                                    <input class="wizard-check-input" type="checkbox" x-model="form.independent_driver" id="independent_driver">
                                                    <span class="wizard-check-indicator" aria-hidden="true"></span>
                                                    <span class="wizard-check-copy">
                                                        <span class="wizard-check-title">Quero avançar com a minha reserva</span>
                                                        <span class="wizard-check-hint">Confirma que pretende reservar uma viatura e seguir para a validação do processo.</span>
                                                    </span>
                                                </label>
                                            </div>
                                        </div>
                                    </template>

                                    <template x-if="s.name === 'vehicle'">
                                        <div class="col-12">
                                            <p class="text-muted mb-3">Escolha a viatura pretendida. Se a reserva vier da página de uma viatura específica, esse modelo já aparece pré-selecionado para si.</p>
                                            <template x-if="preselectedVehicle">
                                                <div class="wizard-info-banner mb-3">
                                                    <strong>Reserva iniciada a partir desta viatura:</strong>
                                                    <span x-text="preselectedVehicle.label"></span>
                                                    <div class="small text-muted mt-1">A seleção já ficou pré-preenchida para si neste passo.</div>
                                                </div>
                                            </template>
                                            <div class="row g-3">
                                                <template x-for="type in vehicleTypes" :key="type.id">
                                                    <div class="col-md-6">
                                                        <label class="d-flex align-items-start gap-3 p-3 border rounded-3 hover-shadow-sm w-100">
                                                            <input type="radio" class="form-check-input mt-1" :value="type.id" x-model="form.vehicle_type_id">
                                                            <div>
                                                                <div class="fw-semibold" x-text="`${type.brand} ${type.model}`"></div>
                                                                <div class="text-muted small" x-text="type.version ? type.version : 'Versão padrão'"></div>
                                                                <div class="text-success fw-semibold" x-text="`Aluguer semanal: €${Number(type.weekly_rental_price).toFixed(2)}`"></div>
                                                            </div>
                                                        </label>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </template>

                                    <template x-if="s.name === 'rental'">
                                        <div class="col-12">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <div class="p-3 rounded-3 wizard-panel h-100">
                                                        <h6 class="text-success mb-2">O que está incluído</h6>
                                                        <ul class="mb-0 small" style="color:#cbd5e1;">
                                                            <li>Aluguer com manutenção incluída</li>
                                                            <li>Seguro e assistência 24/7</li>
                                                            <li>Faturação transparente</li>
                                                        </ul>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="p-3 rounded-3 wizard-panel h-100">
                                                        <h6 class="text-success mb-2">Antes de confirmar a reserva</h6>
                                                        <ul class="mb-0 small" style="color:#cbd5e1;">
                                                            <li>Leitura completa das condições</li>
                                                            <li>Disponibilidade para pagamento da caução inicial de 250€
                                                                <span class="wizard-tax-popover">
                                                                    <button type="button" class="wizard-tax-link" aria-label="Informação sobre IVA">*</button>
                                                                    <span class="wizard-tax-popover__bubble" role="tooltip">
                                                                        IVA incluido à taxa em vigor.
                                                                    </span>
                                                                </span>
                                                            </li>
                                                            <li>Comunicação rápida com a equipa</li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="wizard-check-grid mt-3">
                                                <label class="wizard-check-card" :class="{ 'is-active': form.rental_terms_read }" for="rental_terms_read">
                                                    <input class="wizard-check-input" type="checkbox" x-model="form.rental_terms_read" id="rental_terms_read">
                                                    <span class="wizard-check-indicator" aria-hidden="true"></span>
                                                    <span class="wizard-check-copy">
                                                        <span class="wizard-check-title">Li e compreendi as condições da reserva</span>
                                                        <span class="wizard-check-hint">Confirme que já leu os pontos principais sobre aluguer, caução e levantamento da viatura.</span>
                                                    </span>
                                                </label>
                                                <label class="wizard-check-card" :class="{ 'is-active': form.rental_terms_accept }" for="rental_terms_accept">
                                                    <input class="wizard-check-input" type="checkbox" x-model="form.rental_terms_accept" id="rental_terms_accept">
                                                    <span class="wizard-check-indicator" aria-hidden="true"></span>
                                                    <span class="wizard-check-copy">
                                                        <span class="wizard-check-title">Aceito avançar com a reserva nestas condições</span>
                                                        <span class="wizard-check-hint">Sem esta confirmação não conseguimos continuar com a preparação da sua reserva.</span>
                                                    </span>
                                                </label>
                                            </div>
                                        </div>
                                    </template>

                                    <template x-if="s.name === 'eligibility'">
                                        <div class="col-12">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label small wizard-label">Tem curso TVDE?</label>
                                                    <select class="form-select" x-model="form.has_tvde_course">
                                                        <option value="">Selecione</option>
                                                        <option value="1">Sim</option>
                                                        <option value="0">Não</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label small wizard-label">Certificado válido?</label>
                                                    <select class="form-select" x-model="form.certificate_valid">
                                                        <option value="">Selecione</option>
                                                        <option value="1">Sim</option>
                                                        <option value="0">Não</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label small wizard-label">Experiência anterior</label>
                                                    <input type="text" class="form-control" x-model="form.experience" placeholder="Anos, cidades, plataformas">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label small wizard-label">Plataformas</label>
                                                    <div class="d-flex flex-wrap gap-2">
                                                        <div class="form-check form-check-inline">
                                                            <input class="form-check-input" type="checkbox" value="Uber" x-model="form.platforms" id="platform_uber">
                                                            <label class="form-check-label" for="platform_uber">Uber</label>
                                                        </div>
                                                        <div class="form-check form-check-inline">
                                                            <input class="form-check-input" type="checkbox" value="Bolt" x-model="form.platforms" id="platform_bolt">
                                                            <label class="form-check-label" for="platform_bolt">Bolt</label>
                                                        </div>
                                                        <div class="form-check form-check-inline">
                                                            <input class="form-check-input" type="checkbox" value="Outra" x-model="form.platforms" id="platform_other">
                                                            <label class="form-check-label" for="platform_other">Outra</label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>

                                    <template x-if="s.name === 'personal'">
                                        <div class="col-12">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label small wizard-label">Nome completo</label>
                                                    <input type="text" class="form-control" x-model="form.full_name">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label small wizard-label">Email</label>
                                                    <input type="email" class="form-control" x-model="form.email">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label small wizard-label">Telemóvel</label>
                                                    <input type="text" class="form-control" x-model="form.phone">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label small wizard-label">NIF</label>
                                                    <input type="text" class="form-control" x-model="form.nif">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label small wizard-label">IBAN</label>
                                                    <input type="text" class="form-control" x-model="form.iban" placeholder="PT50 0000 0000 0000 0000 0000 0">
                                                </div>
                                            </div>
                                        </div>
                                    </template>

                                    <template x-if="s.name === 'documents'">
                                        <div class="col-12">
                                            <div class="wizard-info-banner mb-3">
                                                <strong>Estes ficheiros não são obrigatórios para avançar.</strong>
                                                <p class="mb-0 mt-2 text-muted">Se os carregar já, conseguimos acelerar a validação e a preparação da reserva. Se preferir, pode enviar a reserva agora e partilhar os documentos mais tarde.</p>
                                            </div>
                                            <div class="row g-3">
                                                <template x-for="doc in documentFields" :key="doc.field">
                                                    <div class="col-md-6">
                                                        <div
                                                            class="p-3 rounded-3 border border-dashed border-secondary bg-light h-100 text-dark"
                                                            @dragover.prevent
                                                            @drop="handleDrop($event, doc.field)"
                                                        >
                                                            <p class="fw-semibold mb-1" x-text="doc.label"></p>
                                                            <p class="small text-muted mb-2">Opcional. Arraste e largue ou clique para selecionar. Limite de 10MB por ficheiro.</p>
                                                            <label class="w-100">
                                                                <input type="file" class="d-none" multiple @change="uploadFile($event, doc.field)">
                                                                <div class="d-flex align-items-center justify-content-between rounded-3 border border-secondary p-2 text-secondary bg-white">
                                                                    <span class="small" x-text="documents[doc.field]?.length ? `${documents[doc.field].length} ficheiro(s)` : 'Selecionar ficheiro'"></span>
                                                                    <i class="fa fa-upload text-success"></i>
                                                                </div>
                                                            </label>
                                                            <template x-if="documents[doc.field]?.length">
                                                                <p class="small text-success mt-2">Submetido</p>
                                                            </template>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </template>

                                    <template x-if="s.name === 'summary'">
                                        <div class="col-12">
                                            <p class="text-muted">Revise os dados e confirme o envio da reserva. O pagamento da caução inicial de 250€
                                                <span class="wizard-tax-popover">
                                                    <button type="button" class="wizard-tax-link" aria-label="Informação sobre IVA">*</button>
                                                    <span class="wizard-tax-popover__bubble" role="tooltip">
                                                        IVA incluido à taxa em vigor.
                                                    </span>
                                                </span>
                                                será o passo seguinte.
                                            </p>
                                            <ul class="list-unstyled mb-0 text-dark">
                                                <li class="mb-1"><span class="wizard-label">Nome:</span> <span x-text="form.full_name"></span></li>
                                                <li class="mb-1"><span class="wizard-label">Email:</span> <span x-text="form.email"></span></li>
                                                <li class="mb-1"><span class="wizard-label">Telemóvel:</span> <span x-text="form.phone"></span></li>
                                                <li class="mb-1"><span class="wizard-label">NIF:</span> <span x-text="form.nif"></span></li>
                                                <li class="mb-1"><span class="wizard-label">IBAN:</span> <span x-text="form.iban"></span></li>
                                                <li class="mb-1"><span class="wizard-label">Viatura escolhida:</span>
                                                    <span x-text="vehicleTypes.find((t) => Number(t.id) === Number(form.vehicle_type_id)) ? `${vehicleTypes.find((t) => Number(t.id) === Number(form.vehicle_type_id)).brand} ${vehicleTypes.find((t) => Number(t.id) === Number(form.vehicle_type_id)).model}` : '-'"></span>
                                                </li>
                                            </ul>

                                            <div class="wizard-panel rounded-4 p-3 p-md-4 mt-4 mb-3">
                                                <h3 class="h6 mb-2 text-success">Faltam só estas 3 confirmações</h3>
                                                <p class="mb-0 text-muted">Marque as três caixas abaixo para conseguir enviar a sua reserva.</p>
                                            </div>
                                            <div class="wizard-check-grid">
                                                <label class="wizard-check-card" :class="{ 'is-active': form.rgpd }" for="rgpd">
                                                    <input class="wizard-check-input" type="checkbox" x-model="form.rgpd" id="rgpd">
                                                    <span class="wizard-check-indicator" aria-hidden="true"></span>
                                                    <span class="wizard-check-copy">
                                                        <span class="wizard-check-title">Aceito o tratamento de dados (RGPD)</span>
                                                        <span class="wizard-check-hint">Autoriza a Zentrum a tratar os seus dados para análise, contacto e gestão da reserva.</span>
                                                    </span>
                                                </label>
                                                <label class="wizard-check-card" :class="{ 'is-active': form.truth_declaration }" for="truth_declaration">
                                                    <input class="wizard-check-input" type="checkbox" x-model="form.truth_declaration" id="truth_declaration">
                                                    <span class="wizard-check-indicator" aria-hidden="true"></span>
                                                    <span class="wizard-check-copy">
                                                        <span class="wizard-check-title">Confirmo que as informações são verdadeiras</span>
                                                        <span class="wizard-check-hint">Declara que os dados e documentos enviados correspondem à realidade.</span>
                                                    </span>
                                                </label>
                                                <label class="wizard-check-card" :class="{ 'is-active': form.contact_authorization }" for="contact_authorization">
                                                    <input class="wizard-check-input" type="checkbox" x-model="form.contact_authorization" id="contact_authorization">
                                                    <span class="wizard-check-indicator" aria-hidden="true"></span>
                                                    <span class="wizard-check-copy">
                                                        <span class="wizard-check-title">Autorizo contacto pela equipa Zentrum</span>
                                                        <span class="wizard-check-hint">Permite que a nossa equipa fale consigo sobre a viatura e os próximos passos.</span>
                                                    </span>
                                                </label>
                                            </div>
                                        </div>
                                    </template>

                                    <template x-if="s.name === 'payment'">
                                        <div class="col-12">
                                            <div class="wizard-info-banner mb-3">
                                                <strong>Pagamento por referência Multibanco.</strong>
                                                <p class="mb-0 mt-2 text-muted" x-text="payment?.message || 'Estamos a preparar a referência para esta reserva.'"></p>
                                            </div>

                                            <div class="wizard-payment-summary">
                                                <div class="wizard-payment-summary__row">
                                                    <span class="wizard-payment-summary__label">Entidade</span>
                                                    <strong class="wizard-payment-summary__value" x-text="payment?.entity || '12133'"></strong>
                                                    <small class="wizard-payment-summary__meta">
                                                        Subentidade:
                                                        <span x-text="payment?.sub_entity || '054'"></span>
                                                    </small>
                                                </div>
                                                <div class="wizard-payment-summary__row">
                                                    <span class="wizard-payment-summary__label">Referência</span>
                                                    <strong class="wizard-payment-summary__value" x-text="paymentLoading ? 'A gerar referência…' : (payment?.reference || 'A gerar automaticamente')"></strong>
                                                    <template x-if="payment?.expires_at">
                                                        <small class="wizard-payment-summary__meta">
                                                            Válida até
                                                            <span x-text="new Date(payment.expires_at).toLocaleString('pt-PT', { dateStyle: 'short', timeStyle: 'short' })"></span>
                                                        </small>
                                                    </template>
                                                </div>
                                                <div class="wizard-payment-summary__row">
                                                    <span class="wizard-payment-summary__label">Valor</span>
                                                    <strong class="wizard-payment-summary__value">
                                                        <span x-text="payment?.formatted_amount || '307,50€'"></span>
                                                        <span class="wizard-tax-popover">
                                                            <button type="button" class="wizard-tax-link" aria-label="Informação sobre IVA">*</button>
                                                            <span class="wizard-tax-popover__bubble" role="tooltip">
                                                                IVA incluido à taxa em vigor.
                                                            </span>
                                                        </span>
                                                    </strong>
                                                    <small class="wizard-payment-summary__meta">
                                                        Caução base:
                                                        <span x-text="payment?.formatted_base_amount || '250,00€'"></span>
                                                        · IVA:
                                                        <span x-text="payment?.formatted_vat_amount || '57,50€'"></span>
                                                    </small>
                                                </div>
                                            </div>

                                            <div class="wizard-panel rounded-4 p-3 p-md-4 mt-3">
                                                <h3 class="h6 mb-2 text-success">Como usar esta referência</h3>
                                                <ul class="mb-0 small">
                                                    <li>Use a entidade, subentidade e referência apresentadas neste passo para liquidar a caução inicial.</li>
                                                    <li>O valor total a pagar já inclui o IVA aplicável à fase inicial da reserva.</li>
                                                    <li>Depois do pagamento, poderemos confirmar a reserva da viatura.</li>
                                                </ul>
                                            </div>

                                            <div class="mt-3">
                                                <button type="button" class="btn btn-outline-secondary" @click="loadPaymentReference(true)" :disabled="paymentLoading">
                                                    <span x-text="paymentLoading ? 'A atualizar…' : 'Atualizar referência'"></span>
                                                </button>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                <div class="d-flex justify-content-between align-items-center mt-4">
                                    <button type="button" class="btn btn-outline-secondary" @click="prevStep" :disabled="stepIndex === 0">
                                        Voltar
                                    </button>
                                    <div class="d-flex align-items-center gap-3">
                                        <small class="text-success" x-text="saveMessage"></small>
                                        <button
                                            type="button"
                                            class="btn btn-success"
                                            @click="stepIndex === steps.length - 1 ? submit() : nextStep()"
                                            x-text="stepIndex === steps.length - 1 ? 'Enviar reserva' : (steps[stepIndex].name === 'documents' ? 'Saltar por agora' : (steps[stepIndex].name === 'summary' ? 'Continuar para pagamento' : 'Avançar'))"
                                        ></button>
                                    </div>
                                </div>
                            </section>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
