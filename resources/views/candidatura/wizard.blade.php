@extends('website.layout')

@section('title', 'Candidatura online | Zentrum TVDE')

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
</style>
@endpush

@push('scripts')
<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script>
    function wizard(config) {
        return {
            steps: [
                { name: 'welcome', title: 'Boas-vindas', subtitle: 'Modelo Zentrum TVDE' },
                { name: 'vehicle', title: 'Escolha de viatura', subtitle: 'Selecione o tipo de viatura pretendido' },
                { name: 'rental', title: 'Condições de aluguer', subtitle: 'Leia e aceite' },
                { name: 'eligibility', title: 'Elegibilidade TVDE', subtitle: 'Requisitos' },
                { name: 'personal', title: 'Dados pessoais', subtitle: 'Contacto e identificação' },
                { name: 'documents', title: 'Documentos', subtitle: 'Uploads obrigatórios' },
                { name: 'legal', title: 'Confirmações legais', subtitle: 'RGPD e autorizações' },
                { name: 'summary', title: 'Conclusão', subtitle: 'Revisão final' },
            ],
            vehicleTypes: config.vehicleTypes || [],
            documentFields: [
                { field: 'document_id', label: 'Documento de identificação' },
                { field: 'driver_license', label: 'Carta de condução' },
                { field: 'tvde_certificate', label: 'Certificado TVDE' },
                { field: 'criminal_record', label: 'Registo criminal' },
            ],
            form: {},
            documents: {},
            token: config.token,
            saveEndpoint: config.saveEndpoint,
            uploadEndpoint: config.uploadEndpoint,
            submitEndpoint: config.submitEndpoint,
            stepIndex: 0,
            saveMessage: '',
            status: config.initial.status,

            get progress() {
                return Math.round(((this.stepIndex + 1) / this.steps.length) * 100);
            },

            get statusLabel() {
                return this.status === 'submitted' ? 'Submetida' : (this.status === 'incomplete' ? 'Incompleta' : 'Rascunho');
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
                this.documentFields.forEach((doc) => {
                    this.documents[doc.field] = this.normalizeDocumentList(this.documents[doc.field]);
                });
                const foundStep = this.steps.findIndex((s) => s.name === (config.initial.current_step || 'welcome'));
                this.stepIndex = foundStep >= 0 ? foundStep : 0;
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
                    return this.documentFields.every((doc) => Array.isArray(this.documents[doc.field]) && this.documents[doc.field].length > 0);
                }
                if (step === 'legal') {
                    return this.form.rgpd && this.form.truth_declaration && this.form.contact_authorization;
                }

                return true;
            },

            async saveCurrentStep() {
                const step = this.steps[this.stepIndex].name;

                if (!this.validateStep(step)) {
                    this.saveMessage = 'Preencha todos os campos obrigatorios';

                    return false;
                }

                this.saveMessage = 'A guardar...';
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
                    this.saveMessage = 'Dados guardados';
                    this.status = this.status === 'submitted' ? 'submitted' : 'incomplete';
                    return true;
                } catch (e) {
                    this.saveMessage = 'Erro a guardar';
                    return false;
                }
            },

            async nextStep() {
                const saved = await this.saveCurrentStep();
                if (!saved) return;

                if (this.stepIndex < this.steps.length - 1) {
                    this.stepIndex += 1;
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
                const allValid = this.steps.every((s) => this.validateStep(s.name === 'summary' ? 'legal' : s.name));
                if (!allValid) {
                    this.saveMessage = 'Preencha todos os campos obrigatorios antes de submeter';

                    return;
                }

                const saved = await this.saveCurrentStep();
                if (!saved) return;

                this.saveMessage = 'A submeter...';
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
                    this.saveMessage = 'Erro na submissão';
                    return;
                }
                this.status = 'submitted';
                this.saveMessage = 'Candidatura submetida com sucesso. Será redirecionado em 5 segundos...';

                let seconds = 5;
                const interval = setInterval(() => {
                    seconds -= 1;
                    this.saveMessage = `Candidatura submetida com sucesso. Será redirecionado em ${seconds} segundos...`;
                    if (seconds <= 0) {
                        clearInterval(interval);
                        window.location.href = '/';
                    }
                }, 1000);
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
                alt="Candidatura Zentrum TVDE"
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
                        uploadEndpoint: '{{ $uploadEndpoint }}',
                        saveEndpoint: '{{ $saveEndpoint }}',
                        submitEndpoint: '{{ $submitEndpoint }}'
                    })"
                    x-init="init()"
                >
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <p class="mb-1 text-muted">Zentrum TVDE</p>
                            <h1 class="h3 mb-0">Candidatura online</h1>
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
                                            <p class="text-muted">Conheça o modelo Zentrum TVDE antes de avançar.</p>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" x-model="form.accepts_model" id="accepts_model">
                                                <label class="form-check-label" for="accepts_model">
                                                    Compreendo que a Zentrum TVDE não celebra contratos de trabalho
                                                </label>
                                            </div>
                                            <div class="form-check mt-2">
                                                <input class="form-check-input" type="checkbox" x-model="form.independent_driver" id="independent_driver">
                                                <label class="form-check-label" for="independent_driver">
                                                    Pretendo avançar como motorista independente
                                                </label>
                                            </div>
                                        </div>
                                    </template>

                                    <template x-if="s.name === 'vehicle'">
                                        <div class="col-12">
                                            <p class="text-muted mb-3">Escolha a viatura pretendida. A disponibilidade pode variar; se não existir stock no momento, será encomendada após validação do seu processo.</p>
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
                                                        <h6 class="text-success mb-2">Condições chave</h6>
                                                        <ul class="mb-0 small" style="color:#cbd5e1;">
                                                            <li>Aluguer com manutenção incluída</li>
                                                            <li>Seguro e assistência 24/7</li>
                                                            <li>Faturação transparente</li>
                                                        </ul>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="p-3 rounded-3 wizard-panel h-100">
                                                        <h6 class="text-success mb-2">O que esperamos</h6>
                                                        <ul class="mb-0 small" style="color:#cbd5e1;">
                                                            <li>Profissionalismo e pontualidade</li>
                                                            <li>Respeito pelas plataformas</li>
                                                            <li>Comunicação clara com a equipa</li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-check mt-3">
                                                <input class="form-check-input" type="checkbox" x-model="form.rental_terms_read" id="rental_terms_read">
                                                <label class="form-check-label" for="rental_terms_read">
                                                    Li e compreendi as condições
                                                </label>
                                            </div>
                                            <div class="form-check mt-2">
                                                <input class="form-check-input" type="checkbox" x-model="form.rental_terms_accept" id="rental_terms_accept">
                                                <label class="form-check-label" for="rental_terms_accept">
                                                    Aceito avançar com base nestas condições
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
                                            <p class="text-muted mb-3">Uploads obrigatórios. Limite 10MB por ficheiro. Pode arrastar e largar diretamente nas caixas.</p>
                                            <div class="row g-3">
                                                <template x-for="doc in documentFields" :key="doc.field">
                                                    <div class="col-md-6">
                                                        <div
                                                            class="p-3 rounded-3 border border-dashed border-secondary bg-light h-100 text-dark"
                                                            @dragover.prevent
                                                            @drop="handleDrop($event, doc.field)"
                                                        >
                                                            <p class="fw-semibold mb-1" x-text="doc.label"></p>
                                                            <p class="small text-muted mb-2">Arraste e largue ou clique para selecionar.</p>
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

                                    <template x-if="s.name === 'legal'">
                                        <div class="col-12">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" x-model="form.rgpd" id="rgpd">
                                                <label class="form-check-label wizard-label" for="rgpd">Li e aceito o tratamento de dados (RGPD)</label>
                                            </div>
                                            <div class="form-check mt-2">
                                                <input class="form-check-input" type="checkbox" x-model="form.truth_declaration" id="truth_declaration">
                                                <label class="form-check-label wizard-label" for="truth_declaration">Declaro que as informações são verdadeiras</label>
                                            </div>
                                            <div class="form-check mt-2">
                                                <input class="form-check-input" type="checkbox" x-model="form.contact_authorization" id="contact_authorization">
                                                <label class="form-check-label wizard-label" for="contact_authorization">Autorizo contacto pela equipa Zentrum</label>
                                            </div>
                                        </div>
                                    </template>

                                    <template x-if="s.name === 'summary'">
                                        <div class="col-12">
                                            <p class="text-muted">Revise os dados e confirme a submissão.</p>
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
                                            x-text="stepIndex === steps.length - 1 ? 'Submeter candidatura' : 'Avançar'"
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

